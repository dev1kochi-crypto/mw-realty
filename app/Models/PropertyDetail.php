<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyDetail extends Model
{
    protected $fillable = [
        'property_id',
        'amenities',
        'year_built',
        'floor',
        'parking',
        'garage',
        'furnished',
        'direct_from_owner',
        'security_deposit',
        'virtual_tour_url',
        'view',
        'easy_access',
        'property_attributes',
        'floor_plan_image',
        'floor_plan_file',
        'extra_attributes',
    ];

    protected $casts = [
        'amenities' => 'array',
        'furnished' => 'boolean',
        'security_deposit' => 'decimal:2',
        'easy_access' => 'array',
        'property_attributes' => 'array',
        'extra_attributes' => 'array',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Icon-repeater rows normalized to {icon, label: {lang => text}} — label is per-language since
     * these are Amenities/Easy Access/Attributes chips shown on the (future) public detail page.
     * Legacy data from before per-language labels existed (a plain string label, or a row that's
     * just a bare string from even before this became an icon repeater) is wrapped into the
     * fallback-locale slot on read, so old data keeps displaying correctly without a migration.
     */
    private static function normalizeIconRows(?array $rows): array
    {
        $fallbackLang = config('app.fallback_locale', 'en');

        return collect($rows ?? [])
            ->map(function ($row) use ($fallbackLang) {
                if (!is_array($row)) {
                    return ['icon' => null, 'label' => [$fallbackLang => (string) $row]];
                }
                $label = $row['label'] ?? '';
                $label = is_array($label) ? array_filter($label, fn ($v) => trim((string) $v) !== '') : [$fallbackLang => $label];
                return ['icon' => $row['icon'] ?? null, 'label' => $label];
            })
            ->filter(fn ($row) => !empty($row['label']))
            ->values()
            ->all();
    }

    public function getAmenitiesAttribute($value): array
    {
        return self::normalizeIconRows($value ? json_decode($value, true) : null);
    }

    public function getEasyAccessAttribute($value): array
    {
        return self::normalizeIconRows($value ? json_decode($value, true) : null);
    }

    public function getPropertyAttributesAttribute($value): array
    {
        return self::normalizeIconRows($value ? json_decode($value, true) : null);
    }
}
