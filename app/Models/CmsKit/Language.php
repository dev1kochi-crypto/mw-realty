<?php

namespace App\Models\CmsKit;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $fillable = ['name', 'code', 'flag_image', 'flag_alt', 'is_default', 'status'];

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}


