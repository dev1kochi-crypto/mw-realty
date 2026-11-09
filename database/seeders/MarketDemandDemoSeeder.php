<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Services\MarketDemand\DemandAggregator;
use App\Services\MarketDemand\DemandSegments;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Demo demand for Property Details › Buyer Demand and CRM › Market Demand (not part of
 * DatabaseSeeder — TEST SERVERS ONLY, run it on demand):
 *   php artisan db:seed --class=MarketDemandDemoSeeder
 *
 * Replaces the last DAYS days of market_demand_daily / market_demand_amenities with made-up
 * activity for every community that has live listings: views, favourites and enquiries per listing
 * segment, searches per bedroom count and price band, and amenity interest. Built so every part of
 * the block has something to show:
 *   - interest grows ~60% over the 6 months (Interest over time);
 *   - one bedroom count per area is "hot" — more interest, and +40% in the last 30 days
 *     (a "Searches for … are up" finding);
 *   - one price band per area draws extra interest (an "Underserved" price range);
 *   - the first few communities get more activity than the rest (High vs Low demand levels).
 * The same seed gives the same numbers every run. To go back to real figures, rebuild from the
 * events that still exist:  php artisan market-demand:aggregate --days=180
 */
class MarketDemandDemoSeeder extends Seeder
{
    private const DAYS = 180;

    public function run(): void
    {
        mt_srand(7);
        $from = today()->subDays(self::DAYS - 1);
        DB::table('market_demand_daily')->where('date', '>=', $from->toDateString())->delete();
        DB::table('market_demand_amenities')->where('date', '>=', $from->toDateString())->delete();

        $areas = Property::where('status', true)->available()->with('details')
            ->get(['id', 'translations', 'listing_type', 'property_type', 'bedrooms', 'price', 'rental_period'])
            ->filter(fn ($p) => DemandSegments::area($p)['community'] && DemandSegments::purpose($p->listing_type))
            ->groupBy(fn ($p) => implode('|', DemandSegments::area($p)) . '|' . DemandSegments::purpose($p->listing_type));

        $rows = [];
        $amenityRows = [];
        $rank = 0;
        foreach ($areas as $key => $listings) {
            [$city, $community, $purpose] = explode('|', $key);
            // A few busy areas, most average, some quiet — so demand levels differ.
            $heat = [2.2, 1.8, 1.5][$rank++] ?? (mt_rand(0, 3) === 0 ? 0.45 : 1.0);

            $segments = $listings->map(fn ($p) => [
                'type' => $p->property_type,
                'beds' => DemandSegments::bedrooms($p->bedrooms),
                'band' => DemandSegments::priceBand($purpose, DemandSegments::annualPrice($p)),
            ])->countBy(fn ($s) => json_encode($s))->map(fn ($n, $s) => json_decode($s, true) + ['n' => $n])->values();

            $beds = $segments->pluck('beds')->filter(fn ($b) => $b !== null)->unique()->values();
            $bands = $segments->pluck('band')->filter(fn ($b) => $b !== null)->unique()->sort()->values();
            $hotBeds = $beds->isNotEmpty() ? $beds[mt_rand(0, $beds->count() - 1)] : null;
            // Usually a band next to the busiest one, so the hot band has few listings → underserved.
            $hotBand = $bands->isNotEmpty() ? min(max(0, $bands[mt_rand(0, $bands->count() - 1)] + (mt_rand(0, 1) ? 1 : -1)), count(config("market_demand.price_bands.{$purpose}")) - 1) : null;
            $amenities = $listings->flatMap(fn ($p) => DemandAggregator::amenityKeys($p))->countBy()->sortDesc()->keys()->take(10)->values();

            for ($d = 0; $d < self::DAYS; $d++) {
                $date = $from->copy()->addDays($d);
                $growth = 1 + 0.6 * $d / self::DAYS;
                $weekend = in_array($date->dayOfWeek, [5, 6], true) ? 1.25 : 1; // Fri / Sat
                $recent = $d >= self::DAYS - 30;
                $day = $date->toDateString();

                foreach ($segments as $s) {
                    $boost = ($s['beds'] === $hotBeds ? ($recent ? 2.2 : 1.6) : 1) * ($s['band'] === $hotBand ? 1.8 : 1);
                    $views = $this->noisy(0.55 * $s['n'] * $heat * $growth * $weekend * $boost);
                    if (!$views) {
                        continue;
                    }
                    $rows[] = $this->row($day, $city, $community, $purpose, $s['type'], $s['beds'], $s['band'], [
                        'views' => $views,
                        'favorites' => $this->noisy($views * 0.09),
                        'enquiries' => $this->noisy($views * 0.06 * ($s['band'] === $hotBand ? 1.5 : 1)),
                    ]);
                }

                // Searches: per bedroom count (no type), plus price-range searches (no bedrooms).
                foreach ($beds as $b) {
                    $n = $this->noisy(0.12 * $listings->count() / max(1, $beds->count()) * $heat * $growth * $weekend * ($b === $hotBeds ? ($recent ? 2.4 : 1.6) : 1));
                    if ($n) {
                        $rows[] = $this->row($day, $city, $community, $purpose, null, $b, null, ['searches' => $n, 'saved_searches' => $this->noisy($n * 0.08)]);
                    }
                }
                foreach (array_keys(config("market_demand.price_bands.{$purpose}")) as $band) {
                    $listed = $bands->contains($band) ? 1 : 0.3;
                    $n = $this->noisy(0.08 * $listings->count() / 4 * $heat * $growth * $listed * ($band === $hotBand ? 2.5 : 1));
                    if ($n) {
                        $rows[] = $this->row($day, $city, $community, $purpose, null, null, $band, ['searches' => $n]);
                    }
                }

                foreach ($amenities as $i => $amenity) {
                    $views = $this->noisy(0.3 * $listings->count() * $heat * $growth / ($i + 1));
                    $searches = $this->noisy(($i < 3 ? 0.4 : 0.1) * $heat * $growth);
                    if ($views || $searches) {
                        $amenityRows[] = ['date' => $day, 'city' => $city, 'community' => $community, 'purpose' => $purpose, 'amenity' => $amenity, 'searches' => $searches, 'views' => $views];
                    }
                }
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('market_demand_daily')->insert($chunk);
        }
        foreach (array_chunk($amenityRows, 1000) as $chunk) {
            DB::table('market_demand_amenities')->insert($chunk);
        }
        Cache::forever('market-demand:version', now()->timestamp);
        Cache::forget('market-demand-areas');

        $this->command?->info(count($rows) . ' demand rows and ' . count($amenityRows) . ' amenity rows for ' . $areas->count() . ' area / purpose pairs.');
    }

    private function row(string $date, ?string $city, ?string $community, string $purpose, ?string $type, ?int $beds, ?int $band, array $metrics): array
    {
        return array_merge([
            'date' => $date, 'city' => $city ?: null, 'community' => $community ?: null, 'purpose' => $purpose,
            'property_type' => $type, 'bedrooms' => $beds, 'price_band' => $band,
            'searches' => 0, 'saved_searches' => 0, 'views' => 0, 'favorites' => 0, 'enquiries' => 0,
        ], $metrics);
    }

    /** A whole number around $mean (Poisson-like for small means). */
    private function noisy(float $mean): int
    {
        if ($mean <= 0) {
            return 0;
        }
        if ($mean > 30) {
            return max(0, (int) round($mean * (0.75 + mt_rand(0, 50) / 100)));
        }
        $l = exp(-$mean);
        $k = 0;
        $p = 1.0;
        do {
            $k++;
            $p *= mt_rand() / mt_getrandmax();
        } while ($p > $l);

        return $k - 1;
    }
}
