<?php

namespace App\Models\CmsKit;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $fillable = [
        'image',
        'image_alt',
        'order_index',
        'status',
        'extra_fields',
        'translations',
    ];

    protected $casts = [
        'status' => 'boolean',
        'extra_fields' => 'array',
        'translations' => 'array',
    ];

    /**
     * Scope for active brands
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}


