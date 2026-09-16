<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyFloorPlan extends Model
{
    protected $fillable = [
        'property_id',
        'label',
        'image',
        'size_from',
        'size_to',
        'price_from',
        'price_to',
        'order_index',
    ];

    protected $casts = [
        'price_from' => 'decimal:2',
        'price_to' => 'decimal:2',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
