<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A display currency for the website switcher. Prices are stored in AED (BASE, rate 1); `rate`
 * is units of this currency per 1 AED, so a price shows as price_aed × rate.
 */
class Currency extends Model
{
    public const BASE = 'AED';
    public const CACHE_KEY = 'public-currencies';

    protected $fillable = ['code', 'name', 'symbol', 'rate', 'is_default', 'status', 'order_index'];

    protected $casts = [
        'rate' => 'float',
        'is_default' => 'boolean',
        'status' => 'boolean',
        'order_index' => 'integer',
    ];

    protected static function booted(): void
    {
        // The public list is cached; any admin change clears it.
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function isBase(): bool
    {
        return $this->code === self::BASE;
    }
}
