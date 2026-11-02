<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyInsightSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Property Details › comparable listings and the Market & Investment panel.
 *
 * Peers are listings of the same property type in the same area (location filter, else emirate).
 * Every figure says where it comes from:
 *   verified — deals closed on the platform (Mark as sold / rented), sold_price / sold_at
 *   asking   — listing prices, so an estimate of the market rather than what was paid
 * Verified figures win when there are enough of them (config property_insights.min_sample).
 * Amounts are AED; rents are annualised from the listing's rental period. See config/property_insights.php.
 */
class PropertyInsightService
{
    private const CACHE_TTL = 3600; // seconds

    /** Rental period → periods per year. A rent listing without one is taken as yearly (the UAE norm). */
    private const PERIODS_PER_YEAR = ['yearly' => 1, 'monthly' => 12, 'weekly' => 52, 'daily' => 365];

    /**
     * Up to $limit listings a buyer would compare this one with: same purpose and type, price within the
     * band, bedrooms within ±1 — ranked by same community, bedrooms, price and distance. Topped up with
     * looser matches (same type or same community) so the slider is never short when the area is thin.
     */
    public function comparables(Property $property, int $limit = 6): Collection
    {
        $band = (float) config('property_insights.comparable_price_band');
        $beds = (int) config('property_insights.comparable_beds');

        $candidates = Property::where('status', true)->available()
            ->where('id', '!=', $property->id)
            ->where('listing_type', $property->listing_type)
            ->where('property_type', $property->property_type)
            ->when($property->location || $property->emirate, fn ($q) => $q->where(function ($q) use ($property) {
                $q->when($property->location, fn ($q) => $q->orWhere('location', $property->location))
                    ->when($property->emirate, fn ($q) => $q->orWhere('emirate', $property->emirate));
            }))
            ->when($property->price > 0, fn ($q) => $q->whereBetween('price', [$property->price * (1 - $band), $property->price * (1 + $band)]))
            ->when($property->bedrooms !== null, fn ($q) => $q->whereBetween('bedrooms', [max(0, $property->bedrooms - $beds), $property->bedrooms + $beds]))
            ->with('details')->take(60)->get();

        $ranked = $candidates->sortBy(fn (Property $c) => $this->comparableScore($property, $c))->take($limit)->values();

        if ($ranked->count() < $limit) {
            $ranked = $ranked->concat(
                Property::where('status', true)->available()
                    ->where('id', '!=', $property->id)
                    ->whereNotIn('id', $ranked->pluck('id'))
                    ->where(fn ($q) => $q->where('property_type', $property->property_type)->orWhere('location', $property->location))
                    ->with('details')->displayOrder()->take($limit - $ranked->count())->get()
            );
        }

        return $ranked;
    }

    /** Lower is closer. Community counts most, then bedrooms, then price, then distance (km). */
    private function comparableScore(Property $subject, Property $candidate): float
    {
        $score = 0.0;
        if ($subject->location && $candidate->location !== $subject->location) {
            $score += 10;
        }
        if ($subject->bedrooms !== null) {
            $score += 3 * abs((int) $candidate->bedrooms - (int) $subject->bedrooms);
        }
        if ($subject->price > 0) {
            $score += 10 * abs((float) $candidate->price - (float) $subject->price) / (float) $subject->price;
        }
        $km = $this->distanceKm($subject, $candidate);

        return $score + ($km === null ? 2 : min($km, 20) / 4);
    }

    private function distanceKm(Property $a, Property $b): ?float
    {
        if ($a->latitude === null || $a->longitude === null || $b->latitude === null || $b->longitude === null) {
            return null;
        }
        [$lat1, $lng1, $lat2, $lng2] = array_map('deg2rad', [(float) $a->latitude, (float) $a->longitude, (float) $b->latitude, (float) $b->longitude]);
        $h = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2) * sin(($lng2 - $lng1) / 2) ** 2;

        return 6371 * 2 * asin(min(1, sqrt($h)));
    }

    /** The Market & Investment panel for a published listing (null when it isn't one). */
    public function forSlug(string $slug): ?array
    {
        $property = Property::where('status', true)->where('slug', $slug)->first();
        if (!$property) {
            return null;
        }

        return Cache::remember("property-insights:{$property->id}:{$property->updated_at?->timestamp}", self::CACHE_TTL, function () use ($property) {
            $insights = $this->build($property);
            $this->snapshot($property, $insights);

            return $insights;
        });
    }

    private function build(Property $property): array
    {
        $min = (int) config('property_insights.min_sample');
        $isRent = $property->listing_type === 'rent';
        $rows = $this->peerRows($property);
        $sale = $rows->where('purpose', 'sale');
        $rent = $rows->where('purpose', 'rent');
        $market = $isRent ? $rent : $sale;

        $subjectAmount = $isRent ? $this->annualise((float) $property->price, $property->rental_period) : (float) $property->price;
        $subjectPsf = $subjectAmount > 0 && $property->sqft > 0 ? $subjectAmount / $property->sqft : null;

        // What this listing "should" cost / rent for, from its peers.
        $benchmark = $this->benchmark($market, $property);
        $rentEstimate = $isRent ? null : $this->benchmark($rent, $property);
        $grossYield = $rentEstimate && $property->price > 0 ? round($rentEstimate['amount'] / $property->price * 100, 2) : null;
        $priceRatio = $subjectPsf && $benchmark && $benchmark['psf'] ? round($subjectPsf / $benchmark['psf'], 3) : null;
        $growth = $this->growth($market);

        return [
            'purpose' => $isRent ? 'rent' : 'sale',
            'area_scope' => $property->location ? 'community' : ($property->emirate ? 'emirate' : 'all'),
            'min_sample' => $min,
            'subject' => [
                'amount' => $subjectAmount ?: null, // annual for rent
                'psf' => $subjectPsf ? round($subjectPsf, 2) : null,
                'sqft' => $property->sqft ?: null,
                'beds' => $property->bedrooms,
            ],
            'market' => [
                'asking' => $this->summary($market->where('source', 'asking')->where('live', true), $property),
                'verified' => $this->summary($market->where('source', 'verified'), $property),
            ],
            'estimates' => [
                'value' => $benchmark,           // sale: market value · rent: market annual rent
                'annual_rent' => $rentEstimate,  // sale only — feeds the yield and cash flow
                'gross_yield' => $grossYield !== null ? ['pct' => $grossYield, 'source' => $rentEstimate['source'], 'count' => $rentEstimate['count']] : null,
                'price_ratio' => $priceRatio !== null ? ['ratio' => $priceRatio, 'source' => $benchmark['source'], 'count' => $benchmark['count']] : null,
                'growth' => $growth,
            ],
            'trend' => $this->trend($market),
            'recent' => $this->recentDeals($market, $property),
            'assessment' => $this->assessment($isRent, $grossYield, $priceRatio, $growth),
            'assumptions' => [
                'service_charge_psf' => (float) (config('property_insights.service_charge_psf.' . $property->property_type) ?? config('property_insights.service_charge_psf.default')),
                'maintenance_pct' => (float) config('property_insights.maintenance_pct'),
                'vacancy_pct' => (float) config('property_insights.vacancy_pct'),
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Same type, same area: listings still on the market, listings published within the window (their
     * asking price) and deals closed within it (what was paid) — one row each with a price per sq.ft.
     */
    private function peerRows(Property $property): Collection
    {
        $cutoff = now()->subMonths((int) config('property_insights.verified_months'));

        return Property::query()
            ->where('id', '!=', $property->id)
            ->where('property_type', $property->property_type)
            ->when($property->location, fn ($q) => $q->where('location', $property->location),
                fn ($q) => $q->when($property->emirate, fn ($q) => $q->where('emirate', $property->emirate)))
            ->where('sqft', '>', 0)
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('status', true)->whereNull('sold_at'))
                ->orWhere('published_at', '>=', $cutoff)
                ->orWhere('sold_at', '>=', $cutoff))
            ->latest('id')->take(3000)
            ->get(['id', 'listing_type', 'rental_period', 'bedrooms', 'sqft', 'price', 'status', 'published_at', 'sold_at', 'sold_type', 'sold_price'])
            ->flatMap(function (Property $p) use ($cutoff) {
                $purpose = $p->listing_type === 'rent' ? 'rent' : 'sale';
                $rows = [];
                if ($p->price > 0) {
                    $amount = $purpose === 'rent' ? $this->annualise((float) $p->price, $p->rental_period) : (float) $p->price;
                    $rows[] = $this->row($p, $purpose, 'asking', $amount, $p->published_at, $p->status && !$p->sold_at);
                }
                if ($p->sold_at && $p->sold_at >= $cutoff && $p->sold_price > 0) {
                    $purpose = $p->sold_type === Property::RENTED ? 'rent' : 'sale';
                    $amount = $purpose === 'rent' ? $this->annualise((float) $p->sold_price, $p->rental_period) : (float) $p->sold_price;
                    $rows[] = $this->row($p, $purpose, 'verified', $amount, $p->sold_at, false);
                }

                return $rows;
            })->values();
    }

    private function row(Property $p, string $purpose, string $source, float $amount, ?Carbon $date, bool $live): array
    {
        return [
            'purpose' => $purpose, 'source' => $source, 'live' => $live, 'beds' => $p->bedrooms,
            'sqft' => (int) $p->sqft, 'amount' => $amount, 'psf' => $amount / $p->sqft, 'date' => $date,
        ];
    }

    private function annualise(float $amount, ?string $period): float
    {
        return $amount * (self::PERIODS_PER_YEAR[$period ?? ''] ?? 1);
    }

    /** Count, median price (same bedrooms when there are enough) and median price per sq.ft. */
    private function summary(Collection $rows, Property $property): ?array
    {
        $min = (int) config('property_insights.min_sample');
        if ($rows->count() < $min) {
            return ['count' => $rows->count()];
        }
        $sameBeds = $property->bedrooms === null ? collect() : $rows->where('beds', $property->bedrooms);

        return [
            'count' => $rows->count(),
            'median_psf' => round($this->median($rows->pluck('psf')), 2),
            'same_beds_count' => $sameBeds->count(),
            'median_amount' => $sameBeds->count() >= $min ? round($this->median($sameBeds->pluck('amount'))) : null,
        ];
    }

    /**
     * Market value (or annual rent) for this listing: median price per sq.ft of the same-bedroom peers
     * (all peers when there are too few) × its size. Closed deals first, asking prices otherwise.
     */
    private function benchmark(Collection $rows, Property $property): ?array
    {
        $min = (int) config('property_insights.min_sample');
        foreach (['verified', 'asking'] as $source) {
            $pool = $rows->where('source', $source);
            if ($source === 'asking') {
                $pool = $pool->where('live', true);
            }
            $sameBeds = $property->bedrooms === null ? collect() : $pool->where('beds', $property->bedrooms);
            $basis = $sameBeds->count() >= $min ? $sameBeds : $pool;
            if ($basis->count() < $min) {
                continue;
            }
            $psf = $this->median($basis->pluck('psf'));
            $amount = $property->sqft > 0 ? $psf * $property->sqft : $this->median($basis->pluck('amount'));

            return [
                'amount' => round($amount, -3), // an estimate — no false precision
                'psf' => round($psf, 2),
                'source' => $source,
                'count' => $basis->count(),
                'same_beds' => $basis === $sameBeds,
            ];
        }

        return null;
    }

    /** Price per sq.ft over the last 12 months vs the 12 before — closed deals when both halves have enough. */
    private function growth(Collection $rows): ?array
    {
        $min = (int) config('property_insights.min_sample');
        $yearAgo = now()->subYear();
        $twoYearsAgo = now()->subYears(2);
        foreach (['verified', 'asking'] as $source) {
            $pool = $rows->where('source', $source)->filter(fn ($r) => $r['date'] && $r['date'] >= $twoYearsAgo);
            $recent = $pool->filter(fn ($r) => $r['date'] >= $yearAgo);
            $before = $pool->filter(fn ($r) => $r['date'] < $yearAgo);
            if ($recent->count() < $min || $before->count() < $min) {
                continue;
            }
            $was = $this->median($before->pluck('psf'));

            return $was > 0 ? [
                'pct' => round(($this->median($recent->pluck('psf')) - $was) / $was * 100, 1),
                'source' => $source,
                'count' => $recent->count() + $before->count(),
            ] : null;
        }

        return null;
    }

    /** Median price per sq.ft per quarter — asking (by publish date) and verified (by deal date). */
    private function trend(Collection $rows): array
    {
        $quarters = (int) config('property_insights.trend_quarters');
        $start = now()->startOfQuarter()->subQuarters($quarters - 1);
        $points = [];
        for ($i = 0; $i < $quarters; $i++) {
            $from = $start->copy()->addQuarters($i);
            $to = $from->copy()->endOfQuarter();
            $in = fn ($source) => $rows->where('source', $source)->filter(fn ($r) => $r['date'] && $r['date']->between($from, $to));
            $asking = $in('asking');
            $verified = $in('verified');
            $points[] = [
                'label' => 'Q' . $from->quarter . ' ' . $from->year,
                'asking_psf' => $asking->count() ? round($this->median($asking->pluck('psf'))) : null,
                'asking_count' => $asking->count(),
                'verified_psf' => $verified->count() ? round($this->median($verified->pluck('psf'))) : null,
                'verified_count' => $verified->count(),
            ];
        }

        return $points;
    }

    /** The last few closed deals — size, bedrooms and price only (no unit, address or parties). */
    private function recentDeals(Collection $rows, Property $property): array
    {
        return $rows->where('source', 'verified')->sortByDesc('date')->take(6)
            ->map(fn ($r) => [
                'date' => $r['date']->toDateString(),
                'beds' => $r['beds'],
                'sqft' => $r['sqft'],
                'amount' => round($r['amount']),
                'psf' => round($r['psf']),
                'same_beds' => $property->bedrooms !== null && $r['beds'] === $property->bedrooms,
            ])->values()->all();
    }

    /**
     * The automated badge: each signal the numbers support, and a headline. Two or more positive
     * signals on a sale make it a "strong investment choice". Null when there's nothing to judge on.
     */
    private function assessment(bool $isRent, ?float $yield, ?float $ratio, ?array $growth): ?array
    {
        $c = config('property_insights');
        $signals = [];
        if ($ratio !== null) {
            if ($ratio <= $c['below_market_ratio']) {
                $signals[] = ['key' => $isRent ? 'good_value_rent' : 'below_market', 'tone' => 'positive', 'value' => round((1 - $ratio) * 100)];
            } elseif ($ratio >= $c['above_market_ratio']) {
                $signals[] = ['key' => $isRent ? 'above_market_rent' : 'above_market', 'tone' => 'caution', 'value' => round(($ratio - 1) * 100)];
            } else {
                $signals[] = ['key' => 'fair_price', 'tone' => 'neutral', 'value' => round(($ratio - 1) * 100)];
            }
        }
        if (!$isRent && $yield !== null) {
            if ($yield >= $c['high_yield_pct']) {
                $signals[] = ['key' => 'high_yield', 'tone' => 'positive', 'value' => $yield];
            } elseif ($yield < $c['low_yield_pct']) {
                $signals[] = ['key' => 'low_yield', 'tone' => 'caution', 'value' => $yield];
            } else {
                $signals[] = ['key' => 'steady_yield', 'tone' => 'neutral', 'value' => $yield];
            }
        }
        if ($growth !== null) {
            if ($growth['pct'] >= $c['strong_growth_pct']) {
                $signals[] = ['key' => 'strong_growth', 'tone' => 'positive', 'value' => $growth['pct']];
            } elseif ($growth['pct'] < 0) {
                $signals[] = ['key' => 'falling_prices', 'tone' => 'caution', 'value' => $growth['pct']];
            }
        }
        if (!$signals) {
            return null;
        }

        $positive = array_values(array_filter($signals, fn ($s) => $s['tone'] === 'positive'));
        $caution = array_values(array_filter($signals, fn ($s) => $s['tone'] === 'caution'));
        if (!$isRent && count($positive) >= 2 && !$caution) {
            $headline = ['key' => 'strong_investment', 'tone' => 'positive'];
        } elseif ($positive) {
            $headline = ['key' => $positive[0]['key'], 'tone' => 'positive'];
        } elseif ($caution) {
            $headline = ['key' => $caution[0]['key'], 'tone' => 'caution'];
        } else {
            $headline = ['key' => $isRent ? 'fair_rent' : 'in_line', 'tone' => 'neutral'];
        }

        return ['headline' => $headline, 'signals' => $signals];
    }

    /**
     * Keep today's estimate for the listing, so once it is sold / rented the estimate can be checked
     * against what was actually paid (PropertyInsightSnapshot).
     */
    private function snapshot(Property $property, array $insights): void
    {
        $value = $insights['estimates']['value'];
        $rent = $insights['purpose'] === 'rent' ? $value : $insights['estimates']['annual_rent'];
        if (!$value && !$rent) {
            return;
        }

        PropertyInsightSnapshot::upsert([[
            'property_id' => $property->id,
            'date' => today()->toDateString(),
            'purpose' => $insights['purpose'],
            'asking_amount' => $insights['subject']['amount'],
            'estimated_value' => $insights['purpose'] === 'sale' ? $value['amount'] ?? null : null,
            'value_source' => $insights['purpose'] === 'sale' ? $value['source'] ?? null : null,
            'estimated_annual_rent' => $rent['amount'] ?? null,
            'rent_source' => $rent['source'] ?? null,
            'sample_size' => ($value['count'] ?? 0) + ($insights['purpose'] === 'sale' ? $rent['count'] ?? 0 : 0),
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['property_id', 'date'], ['asking_amount', 'estimated_value', 'value_source', 'estimated_annual_rent', 'rent_source', 'sample_size', 'updated_at']);
    }

    private function median(Collection $values): float
    {
        $sorted = $values->map(fn ($v) => (float) $v)->sort()->values();
        $n = $sorted->count();
        if (!$n) {
            return 0.0;
        }

        return $n % 2 ? $sorted[intdiv($n, 2)] : ($sorted[$n / 2 - 1] + $sorted[$n / 2]) / 2;
    }
}
