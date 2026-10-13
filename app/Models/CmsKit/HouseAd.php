<?php

namespace App\Models\CmsKit;

/**
 * MW Realty's own ads only (portal_user_id NULL) — what CMS › Ads lists, orders and bulk-edits, so
 * client bookings never get renumbered, toggled or deleted from there (they're reviewed under
 * Client Ads instead).
 */
class HouseAd extends Ad
{
    protected $table = 'ads';

    protected static function booted(): void
    {
        static::addGlobalScope('house', fn ($query) => $query->whereNull('ads.portal_user_id'));
    }
}
