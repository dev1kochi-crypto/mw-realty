<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Per-listing, per-day performance counters — see the create_property_daily_stats_table migration. */
class PropertyDailyStat extends Model
{
    public const IMPRESSIONS = 'impressions';
    public const CLICKS = 'clicks';
    public const LEAD_CLICKS = 'lead_clicks';

    protected $fillable = ['property_id', 'date', 'impressions', 'clicks', 'lead_clicks'];

    protected $casts = [
        'date' => 'date',
    ];

    /** Add 1 to today's $counter for each listing — one upsert, safe under concurrent requests. */
    public static function bump(array $propertyIds, string $counter): void
    {
        $propertyIds = array_values(array_unique(array_map('intval', $propertyIds)));
        if (!$propertyIds || !in_array($counter, [self::IMPRESSIONS, self::CLICKS, self::LEAD_CLICKS], true)) {
            return;
        }

        $now = now();
        static::upsert(
            array_map(fn ($id) => [
                'property_id' => $id,
                'date' => $now->toDateString(),
                $counter => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ], $propertyIds),
            ['property_id', 'date'],
            [$counter => DB::raw("{$counter} + 1"), 'updated_at' => $now],
        );
    }
}
