<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One line of an account's credit ledger (see App\Services\CreditService). Positive rows are lots:
 * `remaining` is what's left of them to spend until `expires_at`.
 */
class CreditTransaction extends Model
{
    public const TOPUP = 'topup';
    public const GRANT = 'grant';
    public const SPEND = 'spend';
    public const REFUND = 'refund';
    public const EXPIRE = 'expire';

    protected $fillable = [
        'account_id', 'actor_type', 'actor_id', 'actor_name', 'type', 'kind', 'credits', 'balance_after', 'remaining', 'expires_at',
        'description', 'property_id', 'reference', 'property_type', 'category', 'location', 'amount', 'vat', 'total', 'stripe_payment_intent_id',
    ];

    protected $casts = [
        'credits' => 'integer',
        'balance_after' => 'integer',
        'remaining' => 'integer',
        'expires_at' => 'datetime',
        'amount' => 'decimal:2',
        'vat' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function account()
    {
        return $this->belongsTo(PortalUser::class, 'account_id');
    }

    public function actor()
    {
        return $this->belongsTo(PortalUser::class, 'actor_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /** Lots that can still be spent. */
    public function scopeSpendable($query)
    {
        return $query->where('credits', '>', 0)->where('remaining', '>', 0)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
