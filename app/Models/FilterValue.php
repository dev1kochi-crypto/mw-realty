<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FilterValue extends Model
{
    protected $fillable = [
        'filter_id',
        'value',
        'translations',
        'order_index',
        'status',
    ];

    protected $casts = [
        'translations' => 'array',
        'status' => 'boolean',
    ];

    public function filter()
    {
        return $this->belongsTo(Filter::class);
    }

    public function getTranslation($attribute, $lang = null)
    {
        $lang = $lang ?? app()->getLocale();
        return $this->translations[$lang][$attribute] ?? ($this->translations[config('app.fallback_locale')][$attribute] ?? null);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
