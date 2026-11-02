<?php

namespace App\Services;

use App\Models\Currency;
use App\Services\Chatbot\ChatbotProvider;
use Illuminate\Support\Facades\Cache;

/**
 * The /compare page's written summary: the key trade-offs between the listings (value for money,
 * size, running costs and yield, location, amenities, buying costs across emirates) and what each
 * one is best for. The AI only sees the PropertyCompareService facts — amounts already in the
 * visitor's currency — and is told to use nothing else. With no AI configured, or when the call
 * fails, the same facts are summarised by fixed rules instead (`source: rules`).
 */
class PropertyCompareSummary
{
    public function __construct(private readonly ChatbotProvider $provider)
    {
    }

    /** @return array{source: string, overview: string, points: string[], best_for: array<int, string>} — best_for keyed by position (1..n) */
    public function summarize(array $compare, Currency $currency, string $lang): array
    {
        $facts = $this->facts($compare, $currency);
        $key = 'compare-summary:' . md5(json_encode($facts)) . ":{$lang}";

        if ($cached = Cache::get($key)) {
            return $cached;
        }

        $summary = $this->provider->isConfigured() ? $this->fromAi($facts, $currency, $lang) : null;
        if ($summary) {
            Cache::put($key, $summary, (int) config('property_compare.summary_cache_ttl'));

            return $summary;
        }

        return $this->fromRules($facts, $currency);
    }

    /** What the AI gets: one compact record per listing, numbered 1..n, amounts in `currency`. */
    private function facts(array $compare, Currency $currency): array
    {
        $rate = $currency->isBase() ? 1.0 : (float) $currency->rate;
        $money = fn ($aed) => $aed === null ? null : round($aed * $rate);

        return [
            'currency' => $currency->code,
            'properties' => collect($compare['properties'])->values()->map(fn ($p, $i) => array_filter([
                'n' => $i + 1,
                'name' => $p['name'],
                'purpose' => $p['listing_type'],
                'type' => $p['type'],
                'status' => $p['completion_status'],
                'location' => $p['location'],
                'emirate' => $p['emirate_label'] ?? $p['emirate'],
                'price' => $money($p['price']),
                'annual_rent' => $money($p['annual_rent']),
                'size_sqft' => $p['sqft'],
                'price_per_sqft' => $p['price_psf'] === null ? null : round($p['price_psf'] * $rate, 2),
                'beds' => $p['beds'],
                'baths' => $p['baths'],
                'furnishing' => $p['furnishing'],
                'parking' => $p['parking'],
                'year_built' => $p['year_built'],
                'amenities' => $p['amenities'],
                'nearest' => collect($p['nearby'])->map(fn ($n) => "{$n['label']}: {$n['distance_km']} km")->values()->all(),
                'buying_or_move_in_costs' => $money($p['costs']['total'] ?? null),
                'total_cash_needed' => $money($p['total_outlay']),
                'estimated_annual_rent' => $money($p['market']['estimated_annual_rent'] ?? null),
                'gross_yield_pct' => $p['market']['gross_yield_pct'] ?? null,
                'net_yield_pct' => $p['market']['net_yield_pct'] ?? null,
                'price_vs_area_pct' => $p['market']['vs_market_pct'] ?? null,
                'area_price_growth_pct' => $p['market']['growth_pct'] ?? null,
                'service_charge_annual' => $money($p['market']['service_charge_annual'] ?? null),
                'golden_visa_eligible' => $p['golden_visa'] ?: null,
            ], fn ($v) => $v !== null && $v !== '' && $v !== [])),
        ];
    }

    private function fromAi(array $facts, Currency $currency, string $lang): ?array
    {
        $system = <<<TXT
You compare property listings for buyers and tenants on MW Realty, a UAE property portal — many of them international.
Use ONLY the facts in the JSON you are given. Never invent prices, distances, amenities, fees or laws; if something isn't in the facts, don't mention it.
Amounts are already in {$currency->code}: write them in {$currency->code}, rounded (e.g. "{$currency->code} 1.25M", "{$currency->code} 850K").
price_vs_area_pct is the listing's price per sq.ft against similar listings in its area (negative = cheaper). Yields and growth are estimates — say so.
Write in the language with code "{$lang}". Plain text inside the JSON strings, no markdown.
Reply with JSON only, exactly this shape:
{"overview": "2 sentences on the main trade-off", "points": ["4 to 6 short points, each naming the listings it compares"], "best_for": {"1": "one short phrase: who listing 1 suits best", "2": "..."}}
TXT;

        $reply = $this->provider->complete($system, json_encode($facts, JSON_UNESCAPED_UNICODE), 1500);
        $json = $reply && preg_match('/\{.*\}/s', $reply, $m) ? json_decode($m[0], true) : null;
        if (!is_array($json) || !is_string($json['overview'] ?? null) || !is_array($json['points'] ?? null)) {
            return null;
        }

        $clean = fn ($s) => trim(str_replace(['**', '__'], '', (string) $s));

        return [
            'source' => 'ai',
            'overview' => $clean($json['overview']),
            'points' => array_values(array_filter(array_map($clean, array_filter($json['points'], 'is_string')))),
            'best_for' => collect($json['best_for'] ?? [])->filter(fn ($v, $k) => is_string($v) && is_numeric($k))
                ->mapWithKeys(fn ($v, $k) => [(int) $k => $clean($v)])->all(),
        ];
    }

    /** The same comparison without AI: cheapest, best value per sq.ft, largest, best yield, most amenities, closest metro. */
    private function fromRules(array $facts, Currency $currency): array
    {
        $props = collect($facts['properties']);
        $code = $currency->code;
        $money = fn ($n) => $code . ' ' . ($n >= 1e6 ? round($n / 1e6, 2) . 'M' : ($n >= 1e3 ? round($n / 1e3) . 'K' : $n));
        $points = [];
        $bestFor = [];

        $priced = $props->filter(fn ($p) => isset($p['price']));
        if ($priced->count() > 1) {
            $low = $priced->sortBy('price')->first();
            $high = $priced->sortByDesc('price')->first();
            $points[] = "{$low['name']} has the lowest asking price ({$money($low['price'])}); {$high['name']} the highest ({$money($high['price'])}).";
            $bestFor[$low['n']] = 'Lowest budget';
        }

        $psf = $props->filter(fn ($p) => isset($p['price_per_sqft']));
        if ($psf->count() > 1) {
            $best = $psf->sortBy('price_per_sqft')->first();
            $worst = $psf->sortByDesc('price_per_sqft')->first();
            $points[] = "Best value for space: {$best['name']} at {$code} " . number_format($best['price_per_sqft']) . "/sq.ft, against {$code} " . number_format($worst['price_per_sqft']) . "/sq.ft for {$worst['name']}.";
            $bestFor[$best['n']] ??= 'Most space for the money';
        }

        $sized = $props->filter(fn ($p) => isset($p['size_sqft']));
        if ($sized->count() > 1) {
            $big = $sized->sortByDesc('size_sqft')->first();
            $points[] = "{$big['name']} is the largest at " . number_format($big['size_sqft']) . ' sq.ft.';
            $bestFor[$big['n']] ??= 'Buyers who need the most space';
        }

        $yields = $props->filter(fn ($p) => isset($p['gross_yield_pct']));
        if ($yields->count()) {
            $top = $yields->sortByDesc('gross_yield_pct')->first();
            $points[] = "Highest estimated rental yield: {$top['name']} at about {$top['gross_yield_pct']}% gross (an estimate from similar listings).";
            $bestFor[$top['n']] ??= 'Investors after rental income';
        }

        $amenities = $props->map(fn ($p) => ['n' => $p['n'], 'name' => $p['name'], 'count' => count($p['amenities'] ?? [])])->filter(fn ($p) => $p['count']);
        if ($amenities->count() > 1) {
            $most = $amenities->sortByDesc('count')->first();
            $points[] = "{$most['name']} lists the most amenities ({$most['count']}).";
            $bestFor[$most['n']] ??= 'Lifestyle and amenities';
        }

        $emirates = $props->pluck('emirate')->filter()->unique();
        if ($emirates->count() > 1) {
            $points[] = 'These listings are in different emirates (' . $emirates->implode(', ') . '), so buying fees differ — see the costs rows.';
        }

        $visa = $props->filter(fn ($p) => !empty($p['golden_visa_eligible']));
        if ($visa->count()) {
            $points[] = $visa->pluck('name')->implode(', ') . ' ' . ($visa->count() > 1 ? 'are' : 'is') . ' priced high enough to qualify for the UAE 10-year Golden Visa.';
        }

        $names = $props->pluck('name');

        return [
            'source' => 'rules',
            'overview' => 'Comparing ' . $names->count() . ' listings: ' . $names->implode(', ') . '.',
            'points' => $points,
            'best_for' => $bestFor,
        ];
    }
}
