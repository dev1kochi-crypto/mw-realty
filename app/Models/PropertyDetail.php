<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyDetail extends Model
{
    protected $fillable = [
        'property_id',
        'amenities',
        'year_built',
        'floor',
        'parking',
        'furnished',
        'view',
        'extra_attributes',
    ];

    protected $casts = [
        'amenities' => 'array',
        'furnished' => 'boolean',
        'extra_attributes' => 'array',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
