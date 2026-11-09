<?php

namespace App\Services\MarketDemand;

use App\Models\Property;

/**
 * How listings and searches are put into demand segments — shared by the aggregator (writing
 * market_demand_daily) and MarketDemandService (reading it), so both sides always agree.
 *
 * Area = the listing's English community and city (translations.en), as stored on the listing.
 * Purpose = sale | rent (off-plan counts as sale). Rents are compared per year.
 */
class DemandSegments
{
    /** Rental period → periods per year (same as PropertyInsightService). */
    private const PERIODS_PER_YEAR = ['yearly' => 1, 'monthly' => 12, 'weekly' => 52, 'daily' => 365];

    /** @return array{city: ?string, community: ?string} */
    public static function area(Property $property): array
    {
        $en = $property->translations['en'] ?? [];

        return [
            'city' => self::clean($en['city'] ?? null),
            'community' => self::clean($en['community'] ?? null),
        ];
    }

    public static function purpose(?string $listingType): ?string
    {
        return match ($listingType) {
            'rent' => 'rent',
            'sale', 'off_plan' => 'sale',
            default => null,
        };
    }

    /** Studio = 0; anything above max_bedrooms is grouped into max ("6+"). */
    public static function bedrooms(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = strtolower(trim((string) $value));
        if ($value === 'studio') {
            return 0;
        }
        if (!preg_match('/^(\d+)\+?$/', $value, $m)) {
            return null;
        }

        return min((int) $m[1], (int) config('market_demand.max_bedrooms'));
    }

    /** The listing's price per year for rent, as listed for sale. */
    public static function annualPrice(Property $property): float
    {
        $price = (float) $property->price;

        return $property->listing_type === 'rent' ? $price * (self::PERIODS_PER_YEAR[$property->rental_period ?? ''] ?? 1) : $price;
    }

    /** Index of the band the amount falls in (config market_demand.price_bands.{purpose}). */
    public static function priceBand(?string $purpose, ?float $amount): ?int
    {
        if (!$purpose || $amount === null || $amount <= 0) {
            return null;
        }
        $band = null;
        foreach (config("market_demand.price_bands.{$purpose}", []) as $i => $from) {
            if ($amount >= $from) {
                $band = $i;
            }
        }

        return $band;
    }

    /** [from, to|null] in AED for a band index. */
    public static function bandRange(string $purpose, int $band): array
    {
        $bands = config("market_demand.price_bands.{$purpose}", []);

        return [$bands[$band] ?? 0, $bands[$band + 1] ?? null];
    }

    public static function clean(?string $value): ?string
    {
        $value = trim(preg_replace('/\s+/', ' ', (string) $value));

        return $value === '' ? null : mb_substr($value, 0, 150);
    }
}
