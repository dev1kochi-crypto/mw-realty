<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CouponRedemption extends Model
{
    protected $fillable = [
        'coupon_id',
        'portal_user_id',
        'plan_id',
        'plan_upgrade_request_id',
        'discount_amount',
        'remaining_payments',
        'used_payments',
        'redeemed_at',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'remaining_payments' => 'integer',
        'used_payments' => 'integer',
        'redeemed_at' => 'datetime',
    ];

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function portalUser()
    {
        return $this->belongsTo(PortalUser::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /** Still discounting future payments (null remaining = forever). */
    public function scopeUsable($query)
    {
        return $query->where(fn ($q) => $q->whereNull('remaining_payments')->orWhere('remaining_payments', '>', 0));
    }
}
