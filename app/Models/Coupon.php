<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Discount code for paid plans, entered by an Agent/Company on the portal Plans page and
 * redeemed when Super Admin approves that plan request. See App\Services\CouponService.
 */
class Coupon extends Model
{
    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED = 'fixed';

    public const DURATIONS = [
        'once' => 'First payment only',
        'repeating' => 'For a number of months',
        'forever' => 'Every payment',
    ];

    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'plan_ids',
        'duration',
        'duration_months',
        'starts_at',
        'expires_at',
        'max_uses',
        'max_uses_per_user',
        'status',
        'stripe_coupon_id',
        'stripe_coupon_signature',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'plan_ids' => 'array',
        'duration_months' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'max_uses' => 'integer',
        'max_uses_per_user' => 'integer',
        'status' => 'boolean',
    ];

    public function redemptions()
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = strtoupper(trim((string) $value));
    }

    public function appliesToPlan(Plan $plan): bool
    {
        return empty($this->plan_ids) || in_array($plan->id, array_map('intval', $this->plan_ids), true);
    }

    /** Discount in AED for a given plan price, never more than the price itself. */
    public function discountFor(float $price): float
    {
        $discount = $this->discount_type === self::TYPE_PERCENT
            ? $price * min(100, (float) $this->discount_value) / 100
            : (float) $this->discount_value;

        return round(min($price, max(0, $discount)), 2);
    }

    public function discountLabel(): string
    {
        return $this->discount_type === self::TYPE_PERCENT
            ? rtrim(rtrim(number_format($this->discount_value, 2), '0'), '.') . '% off'
            : 'AED ' . number_format($this->discount_value, 0) . ' off';
    }

    public function durationLabel(): string
    {
        return match ($this->duration) {
            'repeating' => 'for ' . $this->duration_months . ' month' . ($this->duration_months === 1 ? '' : 's'),
            'forever' => 'on every payment',
            default => 'on the first payment',
        };
    }

    /** active | scheduled | expired | exhausted | inactive — for the admin list badge. */
    public function state(?int $usedCount = null): string
    {
        $used = $usedCount ?? $this->redemptions()->count();

        return match (true) {
            !$this->status => 'inactive',
            $this->expires_at && $this->expires_at->isPast() => 'expired',
            $this->starts_at && $this->starts_at->isFuture() => 'scheduled',
            $this->max_uses !== null && $used >= $this->max_uses => 'exhausted',
            default => 'active',
        };
    }
}
