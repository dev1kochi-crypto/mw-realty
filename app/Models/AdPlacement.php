<?php

namespace App\Models;

use App\Models\CmsKit\Ad;
use Illuminate\Database\Eloquent\Model;

/**
 * Per page of the website (Ad::PLACEMENTS): whether clients can book ads there, how many client ads
 * can run at the same time (the slider's paid slots — MW Realty's own ads don't count), and what an
 * hour / a day costs in credits. Edited in CMS › Ads › Placements.
 */
class AdPlacement extends Model
{
    protected $fillable = ['placement', 'client_bookable', 'max_slots', 'credits_per_hour', 'credits_per_day'];

    protected $casts = [
        'client_bookable' => 'boolean',
        'max_slots' => 'integer',
        'credits_per_hour' => 'integer',
        'credits_per_day' => 'integer',
    ];

    /** Every placement, creating a row (closed to clients) for one added to Ad::PLACEMENTS later. */
    public static function catalog()
    {
        $rows = static::query()->get()->keyBy('placement');
        foreach (array_keys(Ad::PLACEMENTS) as $key) {
            if (!$rows->has($key)) {
                $rows[$key] = static::create(['placement' => $key, 'client_bookable' => false]);
            }
        }

        // In Ad::PLACEMENTS order. (A plain collection: an Eloquent one's only() filters by primary key.)
        return collect(array_keys(Ad::PLACEMENTS))->mapWithKeys(fn ($key) => [$key => $rows[$key]]);
    }

    public static function for(string $placement): ?self
    {
        return static::catalog()->get($placement);
    }

    public function label(): string
    {
        return Ad::PLACEMENTS[$this->placement] ?? $this->placement;
    }

    /** Credits for a booking of $hours: by the day from 24 hours (whole days), by the hour below. */
    public function cost(int $hours): int
    {
        return $hours >= 24 ? (int) ceil($hours / 24) * $this->credits_per_day : $hours * $this->credits_per_hour;
    }
}
