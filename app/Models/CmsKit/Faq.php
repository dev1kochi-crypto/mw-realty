<?php

namespace App\Models\CmsKit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Faq extends Model
{
    protected $fillable = [
        'translations',
        'order_index',
        'status',
        'extra_fields',
        'faqable_type',
        'faqable_id',
    ];

    protected $casts = [
        'translations' => 'array',
        'status' => 'boolean',
        'extra_fields' => 'array',
    ];

    /**
     * Get the parent faqable model (product, service, etc.).
     */
    public function faqable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Helper to get translated attribute
     */
    public function getTranslation($attribute, $lang = null)
    {
        $lang = $lang ?? app()->getLocale();
        return $this->translations[$lang][$attribute] ?? ($this->translations[config('app.fallback_locale')][$attribute] ?? null);
    }

    /**
     * Scope for active FAQs
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}


