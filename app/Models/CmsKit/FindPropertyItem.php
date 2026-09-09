<?php

namespace App\Models\CmsKit;

use App\Models\Property;
use Illuminate\Database\Eloquent\Model;

class FindPropertyItem extends Model
{
    protected $fillable = [
        'image',
        'image_alt',
        'translations',
        'property_type',
        'order_index',
        'status',
    ];

    protected $casts = [
        'translations' => 'array',
        'status' => 'boolean',
    ];

    public function getTranslation($attribute, $lang = null)
    {
        $lang = $lang ?? app()->getLocale();
        return $this->translations[$lang][$attribute] ?? ($this->translations[config('app.fallback_locale')][$attribute] ?? null);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Live count of active properties under this card's property_type — always
     * current, never stored, so it can't go stale as listings change.
     */
    public function propertyCount(): int
    {
        if (!$this->property_type) {
            return 0;
        }

        return Property::active()->where('property_type', $this->property_type)->count();
    }
}
