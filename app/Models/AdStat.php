<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Per-ad, per-hour counters for the advertising report — see the add_client_advertising migration. */
class AdStat extends Model
{
    public const IMPRESSIONS = 'impressions';
    public const CLICKS = 'clicks';

    protected $fillable = ['ad_id', 'date', 'hour', 'impressions', 'clicks'];

    protected $casts = [
        'date' => 'date',
        'hour' => 'integer',
        'impressions' => 'integer',
        'clicks' => 'integer',
    ];

    /** Add 1 to this hour's $counter for each ad — one upsert, safe under concurrent requests. */
    public static function bump(array $adIds, string $counter): void
    {
        $adIds = array_values(array_unique(array_map('intval', $adIds)));
        if (!$adIds || !in_array($counter, [self::IMPRESSIONS, self::CLICKS], true)) {
            return;
        }

        $now = now();
        static::upsert(
            array_map(fn ($id) => [
                'ad_id' => $id,
                'date' => $now->toDateString(),
                'hour' => $now->hour,
                $counter => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ], $adIds),
            ['ad_id', 'date', 'hour'],
            [$counter => DB::raw("{$counter} + 1"), 'updated_at' => $now],
        );
    }
}
