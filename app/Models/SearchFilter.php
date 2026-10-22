<?php

namespace App\Models;

use App\Support\SearchFilterFields;
use Illuminate\Database\Eloquent\Model;

/**
 * One website search filter on one listing page (Admin › Filters › Search Filters).
 * `field` is the property field it searches (SearchFilterFields) and the key the API / URL use.
 */
class SearchFilter extends Model
{
    /** Pages with their own filter set => admin label. */
    public const PAGES = [
        'home' => 'Home',
        'properties' => 'Properties',
        'commercial' => 'Commercial',
        'premium' => 'Premium',
        'offplan' => 'Off-plan',
    ];

    /** Display types => [admin label, short help, Font Awesome icon]. */
    public const TYPES = [
        'select' => ['Dropdown', 'Pick one value from a list', 'fa-caret-square-down'],
        'radio' => ['Radio buttons', 'One value, shown as buttons', 'fa-dot-circle'],
        'checkbox' => ['Checkboxes', 'Tick several values', 'fa-check-square'],
        'text' => ['Text box', 'Visitor types a word', 'fa-font'],
        'number' => ['Number', 'One number (e.g. min. bedrooms)', 'fa-hashtag'],
        'range' => ['Range (min – max)', 'From / to numbers', 'fa-arrows-alt-h'],
    ];

    /**
     * The Home banner search has a fixed layout: these filters, in this style, always there. Only
     * their labels and values are edited (field => type). listing_type / completion_status are the tabs.
     */
    public const HOME_FIELDS = [
        'listing_type' => 'radio',
        'completion_status' => 'radio',
        'property_type' => 'select',
        'bedrooms' => 'select',
        'price' => 'range',
    ];

    /** Pages whose filters the admin adds, removes, reorders and copies (everything but Home). */
    public static function listingPages(): array
    {
        return array_diff_key(self::PAGES, ['home' => true]);
    }

    /** Types that show a list of options. */
    public const OPTION_TYPES = ['select', 'radio', 'checkbox'];

    protected $fillable = ['page', 'field', 'type', 'translations', 'options_source', 'settings', 'order_index', 'status'];

    protected $casts = [
        'translations' => 'array',
        'settings' => 'array',
        'status' => 'boolean',
    ];

    public function options()
    {
        return $this->hasMany(SearchFilterOption::class)->orderBy('order_index');
    }

    public function activeOptions()
    {
        return $this->hasMany(SearchFilterOption::class)->where('status', true)->orderBy('order_index');
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeForPage($query, string $page)
    {
        return $query->where('page', $page)->orderBy('order_index');
    }

    public function getTranslation(string $attribute, ?string $lang = null): ?string
    {
        $lang = $lang ?? app()->getLocale();
        return ($this->translations[$lang][$attribute] ?? null)
            ?: ($this->translations[config('app.fallback_locale')][$attribute] ?? null);
    }

    public function label(?string $lang = null): string
    {
        return $this->getTranslation('label', $lang) ?: (SearchFilterFields::get($this->field)['label'] ?? $this->field);
    }

    /** A Home banner filter: only its labels and values can change. */
    public function isFixed(): bool
    {
        return $this->page === 'home';
    }

    public function hasOptions(): bool
    {
        return in_array($this->type, self::OPTION_TYPES, true);
    }

    /** Has an option list: the choice types, plus the price range's quick-select buttons. */
    public function usesOptions(): bool
    {
        return $this->hasOptions() || ($this->type === 'range' && $this->field === 'price');
    }

    public function setting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }
}
