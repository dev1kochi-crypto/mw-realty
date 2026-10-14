<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A hand-made option of a search filter whose options_source is "custom". */
class SearchFilterOption extends Model
{
    protected $fillable = ['search_filter_id', 'value', 'translations', 'order_index', 'status'];

    protected $casts = [
        'translations' => 'array',
        'status' => 'boolean',
    ];

    public function filter()
    {
        return $this->belongsTo(SearchFilter::class, 'search_filter_id');
    }

    public function label(?string $lang = null): string
    {
        $lang = $lang ?? app()->getLocale();
        return ($this->translations[$lang]['label'] ?? null)
            ?: ($this->translations[config('app.fallback_locale')]['label'] ?? null)
            ?: $this->value;
    }
}
