<?php

namespace App\Models\CmsKit;

use Illuminate\Database\Eloquent\Model;

class OurBuilderItem extends Model
{
    protected $fillable = [
        'image',
        'image_alt',
        'order_index',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
