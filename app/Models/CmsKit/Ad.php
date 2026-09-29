<?php

namespace App\Models\CmsKit;

use Illuminate\Database\Eloquent\Model;

class Ad extends Model
{
    protected $fillable = [
        'name',
        'image',
        'image_alt',
        'mobile_image',
        'link_url',
        'placement',
        'starts_at',
        'ends_at',
        'order_index',
        'status',
        'translations',
    ];

    protected $casts = [
        'translations' => 'array',
        'status' => 'boolean',
        'starts_at' => 'date',
        'ends_at' => 'date',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /** Active AND (no date window set, or today falls inside it) — what a frontend placement query should use. */
    public function scopeCurrentlyRunning($query)
    {
        $today = now()->toDateString();

        return $query->active()
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', $today))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', $today));
    }

    public function scopeForPlacement($query, string $placement)
    {
        return $query->where('placement', $placement);
    }

    /** Optional overlay text (eyebrow / title / text) in $lang, falling back to the default language. */
    public function getTranslation(string $field, ?string $lang = null): ?string
    {
        $lang ??= app()->getLocale();
        $value = $this->translations[$lang][$field] ?? null;

        return filled($value) ? $value : (($this->translations[config('app.fallback_locale')][$field] ?? null) ?: null);
    }

    /**
     * One running ad for a page — picked at random when several run there, so they take turns.
     * The shape every frontend ad block reads (image only, or image + overlay text).
     */
    public static function payloadFor(string $placement, ?string $lang = null): ?array
    {
        $ad = static::forPlacement($placement)->currentlyRunning()->inRandomOrder()->first();
        if (!$ad) {
            return null;
        }

        return [
            'name' => $ad->name,
            'image_url' => media_url($ad->image),
            'mobile_image_url' => $ad->mobile_image ? media_url($ad->mobile_image) : null,
            'image_alt' => $ad->image_alt,
            'link_url' => $ad->link_url,
            'eyebrow' => $ad->getTranslation('eyebrow', $lang),
            'title' => $ad->getTranslation('title', $lang),
            'text' => $ad->getTranslation('text', $lang),
        ];
    }
}
