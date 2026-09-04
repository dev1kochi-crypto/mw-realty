<?php

namespace App\Models\CmsKit;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CareerDepartment extends Model
{
    protected $fillable = [
        'translations',
        'stats',
        'extra_fields',
        'order_index',
        'status',
    ];

    protected $casts = [
        'translations' => 'array',
        'stats' => 'array',
        'extra_fields' => 'array',
        'status' => 'boolean',
    ];

    public function getTranslation(string $attribute, ?string $lang = null): ?string
    {
        $lang = $lang ?? app()->getLocale();

        return $this->translations[$lang][$attribute]
            ?? ($this->translations[config('app.fallback_locale')][$attribute] ?? null);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order_index')->orderBy('id');
    }
}
