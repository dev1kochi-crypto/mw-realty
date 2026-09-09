<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyImage extends Model
{
    protected $fillable = [
        'property_id',
        'image',
        'image_alt',
        'order_index',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
