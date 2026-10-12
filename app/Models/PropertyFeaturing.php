<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single "feature this listing" booking — see App\Services\FeaturedListingService. One paid with
 * credits (credit_transaction_id) doesn't use the plan's premium quota.
 */
class PropertyFeaturing extends Model
{
    protected $fillable = ['portal_user_id', 'property_id', 'credit_transaction_id', 'starts_at', 'ends_at', 'stopped_at'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'stopped_at' => 'datetime',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function creditTransaction()
    {
        return $this->belongsTo(CreditTransaction::class);
    }

    /** Bookings that count against the plan's premium quota (not paid with credits). */
    public function scopePlanQuota($query)
    {
        return $query->whereNull('credit_transaction_id');
    }

    public function scopeThisMonth($query)
    {
        return $query->where('starts_at', '>=', now()->startOfMonth());
    }
}
