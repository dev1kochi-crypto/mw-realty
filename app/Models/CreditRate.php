<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * What an add-on costs in credits for listings matching this row. Empty segment / listing type /
 * property type / price bounds match anything; the most specific matching row wins (see forListing()).
 */
class CreditRate extends Model
{
    /** Premium lengths that can be bought with credits => column. */
    public const PREMIUM_DAYS = [7 => 'premium_7', 15 => 'premium_15', 30 => 'premium_30'];

    protected $fillable = ['segment', 'listing_type', 'property_type', 'min_price', 'max_price', 'listing', 'premium_7', 'premium_15', 'premium_30', 'status', 'order_index'];

    protected $casts = [
        'min_price' => 'decimal:2',
        'max_price' => 'decimal:2',
        'listing' => 'integer',
        'premium_7' => 'integer',
        'premium_15' => 'integer',
        'premium_30' => 'integer',
        'status' => 'boolean',
    ];

    /** The rate for a listing's segment / listing type / property type / price, or null when none matches. */
    public static function forListing(?string $segment, ?string $listingType, ?string $propertyType, $price): ?self
    {
        $price = is_numeric($price) ? (float) $price : null;

        return static::where('status', true)->orderBy('order_index')->orderBy('id')->get()
            ->filter(fn (self $rate) => (!$rate->segment || $rate->segment === $segment)
                && (!$rate->listing_type || $rate->listing_type === $listingType)
                && (!$rate->property_type || $rate->property_type === $propertyType)
                && ($rate->min_price === null || ($price !== null && $price >= (float) $rate->min_price))
                && ($rate->max_price === null || ($price !== null && $price <= (float) $rate->max_price)))
            // sortByDesc keeps order_index order among equally specific rows.
            ->sortByDesc(fn (self $rate) => $rate->specificity())
            ->first();
    }

    public function specificity(): int
    {
        return ($this->segment ? 1 : 0) + ($this->listing_type ? 1 : 0) + ($this->property_type ? 2 : 0)
            + ($this->min_price !== null ? 1 : 0) + ($this->max_price !== null ? 1 : 0);
    }

    /** Credits for one add-on: 'listing' or Premium for 7 / 15 / 30 days. */
    public function cost(string $kind, ?int $days = null): int
    {
        return (int) ($kind === 'listing' ? $this->listing : $this->{self::PREMIUM_DAYS[$days]});
    }

    /** ['listing' => n, 'premium' => [7 => n, 15 => n, 30 => n]] */
    public function costs(): array
    {
        return [
            'listing' => (int) $this->listing,
            'premium' => collect(self::PREMIUM_DAYS)->map(fn ($column) => (int) $this->{$column})->all(),
        ];
    }
}
