<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Super Admin's credit pricing (CMS › Credits) — a single row, see current(). */
class CreditSetting extends Model
{
    protected $fillable = ['enabled', 'price_per_credit', 'vat_percent', 'validity_days', 'min_topup', 'max_topup'];

    protected $casts = [
        'enabled' => 'boolean',
        'price_per_credit' => 'decimal:2',
        'vat_percent' => 'decimal:2',
        'validity_days' => 'integer',
        'min_topup' => 'integer',
        'max_topup' => 'integer',
    ];

    public static function current(): self
    {
        return static::query()->first() ?? static::create([]);
    }

    /** ['price_per_credit', 'subtotal', 'vat', 'total'] for buying $credits (AED, 2 decimals). */
    public function priceFor(int $credits): array
    {
        $subtotal = round($credits * (float) $this->price_per_credit, 2);
        $vat = round($subtotal * (float) $this->vat_percent / 100, 2);

        return [
            'price_per_credit' => (float) $this->price_per_credit,
            'subtotal' => $subtotal,
            'vat' => $vat,
            'total' => round($subtotal + $vat, 2),
        ];
    }
}
