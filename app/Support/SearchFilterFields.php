<?php

namespace App\Support;

/**
 * The property fields a website search filter can search, and how.
 *
 *  - storage: "column" (properties.<column>), "details" (property_details.<column>),
 *    "details_list" (property_details.<column> JSON rows [{key, …}]) or "keyword" (title / reference).
 *  - types: the display types the admin may pick for it (SearchFilter::TYPES).
 *  - master: the master option list (filters.key, CRM › Master › Property Options) "auto" options
 *    come from; null = the distinct values found on the listings themselves.
 *  - legacy: PropertiesPageService::applyFilters already reads this field's single-value / comma
 *    list (`?{key}=`) and, for price / sqft, its `?min_` / `?max_` params — SearchFilterQuery
 *    leaves those to it so a filter is never applied twice.
 */
class SearchFilterFields
{
    private const OPTION = ['select', 'radio', 'checkbox'];

    public const FIELDS = [
        'listing_type' => ['label' => 'Purpose (Buy / Rent)', 'group' => 'Listing', 'storage' => 'column', 'column' => 'listing_type', 'types' => ['select', 'radio'], 'master' => 'listing_type', 'legacy' => 'value'],
        'completion_status' => ['label' => 'Completion (Ready / Off-plan)', 'group' => 'Listing', 'storage' => 'column', 'column' => 'completion_status', 'types' => ['select', 'radio'], 'master' => 'completion_status', 'legacy' => 'value'],
        'property_type' => ['label' => 'Property type', 'group' => 'Listing', 'storage' => 'column', 'column' => 'property_type', 'types' => self::OPTION, 'master' => 'property_type', 'legacy' => 'value'],
        'category' => ['label' => 'Category (Residential / Commercial)', 'group' => 'Listing', 'storage' => 'column', 'column' => 'category', 'types' => self::OPTION, 'master' => 'category', 'legacy' => 'value'],
        'rental_period' => ['label' => 'Rental period', 'group' => 'Listing', 'storage' => 'column', 'column' => 'rental_period', 'types' => self::OPTION, 'master' => 'rental_period'],

        'bedrooms' => ['label' => 'Bedrooms', 'group' => 'Size & price', 'storage' => 'column', 'column' => 'bedrooms', 'types' => ['select', 'radio', 'checkbox', 'number', 'range'], 'master' => 'bedrooms', 'legacy' => 'value', 'numeric' => true],
        'bathrooms' => ['label' => 'Bathrooms', 'group' => 'Size & price', 'storage' => 'column', 'column' => 'bathrooms', 'types' => ['select', 'radio', 'checkbox', 'number', 'range'], 'master' => 'bathrooms', 'legacy' => 'value', 'numeric' => true],
        'price' => ['label' => 'Price (AED)', 'group' => 'Size & price', 'storage' => 'column', 'column' => 'price', 'types' => ['range', 'number'], 'legacy' => 'range', 'numeric' => true, 'step' => 50000, 'round' => 1000000],
        'sqft' => ['label' => 'Area (sq.ft)', 'group' => 'Size & price', 'storage' => 'column', 'column' => 'sqft', 'types' => ['range', 'number'], 'legacy' => 'range', 'numeric' => true, 'step' => 50, 'round' => 500],

        'furnishing' => ['label' => 'Furnishing', 'group' => 'Features', 'storage' => 'details', 'column' => 'furnished', 'types' => self::OPTION, 'master' => 'furnishing'],
        'amenity' => ['label' => 'Amenities', 'group' => 'Features', 'storage' => 'details_list', 'column' => 'amenities', 'types' => self::OPTION, 'master' => 'amenity'],
        'easy_access' => ['label' => 'Easy access', 'group' => 'Features', 'storage' => 'details_list', 'column' => 'easy_access', 'types' => self::OPTION, 'master' => 'easy_access'],
        'property_attribute' => ['label' => 'Property attributes', 'group' => 'Features', 'storage' => 'details_list', 'column' => 'property_attributes', 'types' => self::OPTION, 'master' => 'property_attribute'],
        'view' => ['label' => 'View', 'group' => 'Features', 'storage' => 'details', 'column' => 'view', 'types' => ['select', 'radio', 'checkbox', 'text'], 'master' => null],
        'developer' => ['label' => 'Developer', 'group' => 'Features', 'storage' => 'details', 'column' => 'developer', 'types' => ['select', 'radio', 'checkbox', 'text'], 'master' => null],
        'parking' => ['label' => 'Parking spaces', 'group' => 'Features', 'storage' => 'details', 'column' => 'parking', 'types' => ['select', 'radio', 'number', 'range'], 'master' => null, 'numeric' => true, 'step' => 1, 'round' => 5],
        'year_built' => ['label' => 'Year built', 'group' => 'Features', 'storage' => 'details', 'column' => 'year_built', 'types' => ['select', 'number', 'range'], 'master' => null, 'numeric' => true, 'step' => 1, 'round' => 1],

        'location' => ['label' => 'Location search box', 'group' => 'Search', 'storage' => 'page', 'column' => null, 'types' => ['text'], 'master' => null, 'legacy' => 'value'],
        'keyword' => ['label' => 'Keyword (title / reference)', 'group' => 'Search', 'storage' => 'keyword', 'column' => null, 'types' => ['text'], 'master' => null],
        'reference_no' => ['label' => 'Reference number', 'group' => 'Search', 'storage' => 'column', 'column' => 'reference_no', 'types' => ['text'], 'master' => null],

        // Page controls with a fixed set of values the listing code understands: the admin picks which
        // show, renames and orders them ("My own list"), but can't invent new ones.
        'sort' => ['label' => 'Sort order', 'group' => 'Page controls', 'storage' => 'page', 'column' => null, 'types' => ['select'], 'master' => null, 'legacy' => 'value', 'fixed' => [
            'default' => ['Recommended', ['properties_listing.toolbar.sort_recommended', 'commercial.filter_panel.sort_recommended']],
            'recent' => ['Most recent', ['properties_listing.toolbar.sort_most_recent', 'commercial.filter_panel.sort_recent']],
            'price_asc' => ['Price: low to high', ['properties_listing.toolbar.sort_price_low_high', 'commercial.filter_panel.sort_price_low_high']],
            'price_desc' => ['Price: high to low', ['properties_listing.toolbar.sort_price_high_low', 'commercial.filter_panel.sort_price_high_low']],
            'popular' => ['Most popular', ['properties_listing.toolbar.sort_most_popular']],
        ]],
        'features' => ['label' => 'Property features (floor plans / open house)', 'group' => 'Page controls', 'storage' => 'page', 'column' => null, 'types' => ['checkbox'], 'master' => null, 'legacy' => 'value', 'fixed' => [
            'floor_plans' => ['Properties with floor plans', ['properties_listing.filter_panel.feature_floor_plans']],
            'open_house' => ['Open house properties', ['properties_listing.filter_panel.feature_open_house_properties']],
        ]],
        'amenities' => ['label' => 'Amenity shortcuts (grouped)', 'group' => 'Page controls', 'storage' => 'page', 'column' => null, 'types' => ['checkbox'], 'master' => null, 'legacy' => 'value', 'fixed' => [
            'furnished' => ['Furnished', ['properties_listing.filter_panel.amenity_furnished', 'commercial.filter_panel.amenity_furnished']],
            'parking' => ['Parking', ['properties_listing.filter_panel.amenity_parking', 'commercial.filter_panel.amenity_parking']],
            'community-pool' => ['Community Pool', ['properties_listing.filter_panel.amenity_community_pool', 'commercial.filter_panel.amenity_community_pool']],
            'gym' => ['Gym', ['properties_listing.filter_panel.amenity_gym', 'commercial.filter_panel.amenity_gym']],
            'security' => ['24/7 Security', ['properties_listing.filter_panel.amenity_security', 'commercial.filter_panel.amenity_security']],
            'balcony' => ['Balcony', ['properties_listing.filter_panel.amenity_balcony', 'commercial.filter_panel.amenity_balcony']],
            'concierge' => ['Concierge', ['properties_listing.filter_panel.amenity_concierge', 'commercial.filter_panel.amenity_concierge']],
            'private-pool' => ['Private Pool', ['properties_listing.filter_panel.amenity_private_pool', 'commercial.filter_panel.amenity_private_pool']],
            'pantry' => ['Pantry', ['commercial.filter_panel.amenity_pantry']],
            'meeting-rooms' => ['Meeting Rooms', ['commercial.filter_panel.amenity_meeting_rooms']],
            'metro' => ['Metro Access', ['commercial.filter_panel.amenity_metro']],
            'central-ac' => ['Central A/C', ['commercial.filter_panel.amenity_central_ac']],
            'loading-bay' => ['Loading Bay', ['commercial.filter_panel.amenity_loading_bay']],
        ]],
    ];

    /**
     * Price quick-select buttons (a price range filter's options, value "min-max", empty max = and up).
     * Labels from the static translations of the page's section.
     */
    public const PRICE_PRESETS = [
        '0-500000' => ['Under 500K', 'price_presets.under_500k'],
        '500000-1000000' => ['500K – 1M', 'price_presets.500k_1m'],
        '1000000-2000000' => ['1M – 2M', 'price_presets.1m_2m'],
        '2000000-5000000' => ['2M – 5M', 'price_presets.2m_5m'],
        '5000000-10000000' => ['5M – 10M', 'price_presets.5m_10m'],
        '10000000-' => ['10M+', 'price_presets.10m_plus'],
    ];

    /** Plain-language help for the admin form: field => [what visitors can do, example values]. */
    public const HELP = [
        'listing_type' => ['Visitors choose whether they want to buy or rent.', 'Buy · Rent'],
        'completion_status' => ['Visitors choose ready-to-move-in homes or projects still being built.', 'Ready · Off-plan'],
        'property_type' => ['Visitors choose the kind of property.', 'Apartment · Villa · Townhouse'],
        'category' => ['Visitors choose residential or commercial listings.', 'Residential · Commercial'],
        'rental_period' => ['Visitors choose how the rent is paid (rent listings).', 'Yearly · Monthly'],
        'bedrooms' => ['Visitors choose how many bedrooms.', 'Studio · 1 · 2 · 3 · 5+'],
        'bathrooms' => ['Visitors choose how many bathrooms.', '1 · 2 · 3 · 5+'],
        'price' => ['Visitors set a price range (AED, shown in their own currency).', 'AED 1M – 3M'],
        'sqft' => ['Visitors set a size range in square feet.', '800 – 2,000 sq.ft'],
        'furnishing' => ['Visitors choose furnished or unfurnished homes.', 'Furnished · Semi-furnished · Unfurnished'],
        'amenity' => ['Visitors tick amenities — the full list agents tick on a listing.', 'Private pool · Gym · Balcony'],
        'easy_access' => ['Visitors tick what should be close by.', 'Metro · Mall · School'],
        'property_attribute' => ['Visitors tick special features of the unit.', 'Corner unit · High floor'],
        'view' => ['Visitors choose the view from the property.', 'Sea view · Burj Khalifa view'],
        'developer' => ['Visitors choose (or type) the developer.', 'Emaar · Nakheel'],
        'parking' => ['Visitors choose how many parking spaces.', 'At least 2'],
        'year_built' => ['Visitors choose when the building was built.', 'Built 2020 or later'],
        'location' => ['The search box at the top where visitors type a community, city or address.', 'Dubai Marina'],
        'keyword' => ['A box to search listing titles and reference numbers.', '"sea view villa"'],
        'reference_no' => ['A box to find one listing by its reference number.', 'PROP021'],
        'sort' => ['The "Sort by" menu.', 'Recommended · Most recent · Price'],
        'features' => ['Tick boxes for listings with floor plans or an open house.', 'Floor plans · Open house'],
        'amenities' => ['A short list of popular amenity tick boxes.', 'Parking · Gym · 24/7 Security'],
    ];

    /** Static-translation section a page's built-in texts live in. */
    public static function textSection(string $page): string
    {
        return match ($page) {
            'home' => 'home',
            'commercial' => 'commercial',
            default => 'properties_listing',
        };
    }

    public static function all(): array
    {
        return self::FIELDS;
    }

    public static function get(string $field): ?array
    {
        return self::FIELDS[$field] ?? null;
    }

    public static function allows(string $field, string $type): bool
    {
        return in_array($type, self::FIELDS[$field]['types'] ?? [], true);
    }

    /** Fields grouped for the admin picker: group => [field => definition]. */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::FIELDS as $key => $field) {
            $groups[$field['group']][$key] = $field;
        }
        return $groups;
    }
}
