<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanPayment extends Model
{
    protected $fillable = [
        'idempotency_key',
        'portal_user_id',
        'plan_id',
        'plan_name',
        'amount',
        'billing_cycle',
        'period_year',
        'period_month',
        'paid_at',
        'original_amount',
        'discount_amount',
        'coupon_code',
        'stripe_invoice_id',
        'stripe_hosted_invoice_url',
        'status',
        'failure_reason',
        'attempts',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
        'attempts' => 'integer',
    ];

    public const STATUSES = ['paid' => 'Paid', 'failed' => 'Failed'];

    /** Only money actually received — use for every revenue total. */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function portalUser()
    {
        return $this->belongsTo(PortalUser::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function getPeriodLabelAttribute(): string
    {
        if ($this->period_month) {
            return \Carbon\Carbon::create($this->period_year, $this->period_month, 1)->format('F Y');
        }

        return (string) $this->period_year;
    }
}
