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
        'security_deposit' => 'decimal:2',
        'easy_access' => 'array',
        'property_attributes' => 'array',
        'extra_attributes' => 'array',
    ];

    /** Value that means "not furnished" in the furnishing option list; every other set value counts as furnished. */
    public const UNFURNISHED = 'unfurnished';

    /** `furnished` holds a furnishing option value (CRM › Master › Property Options), e.g. "semi_furnished". */
    public function isFurnished(): bool
    {
        return filled($this->furnished) && $this->furnished !== self::UNFURNISHED;
    }

    /** Older callers (seeders, imports) still pass true / false / "1" / "0" — stored as the matching option. */
    public function setFurnishedAttribute($value): void
    {
        $this->attributes['furnished'] = match (true) {
            $value === null || $value === '' => null,
            $value === true || $value === 1 || $value === '1' => 'furnished',
            $value === false || $value === 0 || $value === '0' => self::UNFURNISHED,
            default => (string) $value,
        };
    }

    /** The option's label in the current language ("Semi-furnished"), or null when not set. */
    public function furnishingLabel(?string $lang = null): ?string
    {
        if (blank($this->furnished)) {
            return null;
        }
        $option = FilterValue::whereHas('filter', fn ($q) => $q->where('key', Filter::FURNISHING_KEY))->where('value', $this->furnished)->first();

        return $option?->getTranslation('label', $lang) ?? ucwords(str_replace(['_', '-'], ' ', $this->furnished));
    }

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
