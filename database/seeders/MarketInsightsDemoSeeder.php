<?php

namespace Database\Seeders;

use App\Models\Property;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Demo market history for Property Details › Market Insights (not part of DatabaseSeeder — LOCAL
 * ONLY, run it on demand):
 *   php artisan db:seed --class=MarketInsightsDemoSeeder
 *
 * For every community + property type that has live listings it adds, over the last 24 months:
 *   closed sales and rentals (sold_at / sold_price — the "verified" figures), and
 *   older listings that were taken down unsold (their asking price — the asking trend).
 * Prices follow the area's real median price per sq.ft today, about 6% a year lower going back, with
 * ±5% noise; rents sit at a ~7% gross yield. Every row is unpublished (status off), copied from one
 * of the area's listings, and tagged reference_no "MI-DEMO-…" — a re-run deletes them first.
 */
class MarketInsightsDemoSeeder extends Seeder
{
    private const TAG = 'MI-DEMO-';
    // Per area. Spread evenly over the 8 quarters so every quarter of the trend has a point.
    private const SALES = 12;
    private const RENTALS = 10;
    private const OLD_SALE_LISTINGS = 8;
    private const OLD_RENT_LISTINGS = 8;
    private const GROWTH_PER_QUARTER = 0.015;
    private const GROSS_YIELD = 0.07;

    private int $seq = 0;

    public function run(): void
    {
        $removed = Property::where('reference_no', 'like', self::TAG . '%')->delete();
        $this->command?->info("Removed {$removed} earlier demo rows.");

        $live = Property::where('status', true)->whereNull('sold_at')
            ->whereNotNull('location')->whereNotNull('property_type')
            ->where('sqft', '>', 0)->where('price', '>', 0)
            ->get();

        $created = 0;
        foreach ($live->groupBy(fn (Property $p) => "{$p->property_type}|{$p->location}") as $group) {
            $salePsf = $this->medianPsf($group->where('listing_type', '!=', 'rent'));
            $rentPsf = $this->medianPsf($group->where('listing_type', 'rent'));
            // An area with only one kind of listing borrows the other from the typical yield.
            $salePsf ??= $rentPsf / self::GROSS_YIELD;
            $rentPsf ??= $salePsf * self::GROSS_YIELD;

            for ($i = 0; $i < self::SALES; $i++) {
                $created += $this->add($group->random(), 'sale', $salePsf, closed: true, slot: $i);
            }
            for ($i = 0; $i < self::RENTALS; $i++) {
                $created += $this->add($group->random(), 'rent', $rentPsf, closed: true, slot: $i);
            }
            for ($i = 0; $i < self::OLD_SALE_LISTINGS; $i++) {
                $created += $this->add($group->random(), 'sale', $salePsf, closed: false, slot: $i);
            }
            for ($i = 0; $i < self::OLD_RENT_LISTINGS; $i++) {
                $created += $this->add($group->random(), 'rent', $rentPsf, closed: false, slot: $i);
            }
        }

        // The panel is cached per listing (PropertyInsightService::forSlug) — show the new data now.
        Cache::flush();
        $this->command?->info("Added {$created} demo deals / past listings across {$live->groupBy(fn ($p) => "{$p->property_type}|{$p->location}")->count()} areas.");
    }

    /** Annual price (rent: annual rent) per sq.ft, median — null when there are none. */
    private function medianPsf(Collection $listings): ?float
    {
        $psf = $listings->map(function (Property $p) {
            $amount = (float) $p->price;
            if ($p->listing_type === 'rent') {
                $amount *= ['monthly' => 12, 'weekly' => 52, 'daily' => 365][$p->rental_period] ?? 1;
            }

            return $amount / $p->sqft;
        })->sort()->values();

        return $psf->count() ? (float) $psf[intdiv($psf->count(), 2)] : null;
    }

    private function add(Property $template, string $purpose, float $psfToday, bool $closed, int $slot): int
    {
        // Quarter `slot % 8` back from this one (a random day in it, never in the future); the price was
        // lower the further back it is.
        $quartersAgo = $slot % 8;
        $start = now()->startOfQuarter()->subQuarters($quartersAgo);
        $end = $quartersAgo === 0 ? now()->subDay() : $start->copy()->endOfQuarter();
        $date = Carbon::createFromTimestamp(random_int($start->timestamp, max($start->timestamp, $end->timestamp)))->setTime(random_int(9, 18), random_int(0, 59));
        $psf = $psfToday * (1 - self::GROWTH_PER_QUARTER * $quartersAgo) * (random_int(95, 105) / 100);
        $sqft = max(300, (int) round($template->sqft * random_int(85, 115) / 100));
        $amount = round($psf * $sqft / 1000) * 1000; // annual for rent

        $row = $template->replicate();
        $n = ++$this->seq;
        $row->fill([
            'slug' => 'mi-demo-' . $n . '-' . substr(md5(uniqid('', true)), 0, 6),
            'reference_no' => self::TAG . str_pad((string) $n, 5, '0', STR_PAD_LEFT),
            'listing_type' => $purpose,
            'rental_period' => $purpose === 'rent' ? 'yearly' : null,
            'sqft' => $sqft,
            // Asked a little above what it closed at.
            'price' => $closed ? round($amount * random_int(102, 106) / 100 / 1000) * 1000 : $amount,
            'status' => false,
            'featured' => false,
            'featured_from' => null,
            'featured_until' => null,
            'permit_number' => null,
            'available_dates' => null,
            'published_at' => $closed ? $date->copy()->subDays(random_int(20, 90)) : $date,
            'sold_at' => $closed ? $date : null,
            'sold_type' => $closed ? ($purpose === 'rent' ? Property::RENTED : Property::SOLD) : null,
            'sold_price' => $closed ? $amount : null,
            'sold_commission' => null,
            'rented_until' => $closed && $purpose === 'rent' ? $date->copy()->addYear()->toDateString() : null,
            'sold_lead_id' => null,
            'sold_agent_id' => null,
            'sold_notes' => 'Market Insights demo data',
            'status_before_sold' => $closed ? false : null,
        ]);
        $row->saveQuietly();

        return 1;
    }
}
