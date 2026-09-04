<?php

namespace App\Models\CmsKit;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Career extends Model
{
    protected $fillable = [
        'slug',
        'job_type',
        'department',
        'location',
        'country',
        'base',
        'published_date',
        'order_index',
        'status',
        'translations',
        'metadata',
        'extra_fields',
    ];

    protected $casts = [
        'published_date' => 'date',
        'status' => 'boolean',
        'translations' => 'array',
        'metadata' => 'array',
        'extra_fields' => 'array',
    ];

    public function getTranslation($attribute, $lang = null)
    {
        $lang = $lang ?? app()->getLocale();

        return $this->translations[$lang][$attribute]
            ?? ($this->translations[config('app.fallback_locale')][$attribute] ?? null);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order_index')->orderByDesc('published_date')->orderByDesc('id');
    }

    public function scopeApplyFrontendFilters(Builder $query, array $filters = []): Builder
    {
        foreach (['job_type', 'department', 'location', 'country', 'base'] as $field) {
            $value = trim((string) ($filters[$field] ?? ''));

            if ($value !== '') {
                $query->where($field, $value);
            }
        }

        return $query;
    }
}
