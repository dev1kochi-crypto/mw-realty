<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The market estimate Property Details showed for a listing on a given day (PropertyInsightService).
 * Compared with the listing's sold_price once it closes, to measure how accurate the estimates are.
 */
class PropertyInsightSnapshot extends Model
{
    protected $fillable = [
        'property_id', 'date', 'purpose', 'asking_amount', 'estimated_value', 'value_source',
        'estimated_annual_rent', 'rent_source', 'sample_size',
    ];

    protected $casts = [
        'date' => 'date',
        'asking_amount' => 'decimal:2',
        'estimated_value' => 'decimal:2',
        'estimated_annual_rent' => 'decimal:2',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
