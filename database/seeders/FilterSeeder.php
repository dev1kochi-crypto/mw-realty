<?php

namespace Database\Seeders;

use App\Models\Filter;
use App\Models\FilterValue;
use Illuminate\Database\Seeder;

class FilterSeeder extends Seeder
{
    /**
     * Seeds the default property search filters. Safe to re-run.
     * New filters/options going forward are added from the admin "Filters" screen, not here.
     */
    public function run(): void
    {
        $selectFilters = [
            'listing_type' => [
                'label' => 'Listing Type',
                'values' => [
                    'sale' => ['en' => 'Buy', 'ar' => 'شراء'],
                    'rent' => ['en' => 'Rent', 'ar' => 'إيجار'],
                ],
            ],
            'completion_status' => [
                'label' => 'Completion Status',
                'values' => [
                    'ready' => ['en' => 'Ready', 'ar' => 'جاهز'],
                    'off_plan' => ['en' => 'Off-Plan', 'ar' => 'على الخريطة'],
                ],
            ],
            'property_type' => [
                'label' => 'Property Type',
                'values' => [
                    'apartment' => ['en' => 'Apartment', 'ar' => 'شقة'],
                    'villa' => ['en' => 'Villa', 'ar' => 'فيلا'],
                    'townhouse' => ['en' => 'Townhouse', 'ar' => 'منزل تاون'],
                    'penthouse' => ['en' => 'Penthouse', 'ar' => 'بنتهاوس'],
                ],
            ],
            'category' => [
                'label' => 'Category',
                'values' => [],
            ],
            'location' => [
                'label' => 'Location',
                'values' => [],
            ],
            // Not a property search filter — powers the Nearby Places "Type" dropdown instead,
            // so it's kept out of the public search bar via an empty show_on.
            'nearby_place_type' => [
                'label' => 'Nearby Place Type',
                'show_on' => [],
                'values' => [
                    'school' => ['en' => 'School', 'ar' => 'مدرسة'],
                    'hospital' => ['en' => 'Hospital', 'ar' => 'مستشفى'],
                    'restaurant' => ['en' => 'Restaurant', 'ar' => 'مطعم'],
                    'attraction' => ['en' => 'Attraction', 'ar' => 'معلم سياحي'],
                ],
            ],
        ];

        $order = 1;
        foreach ($selectFilters as $key => $config) {
            $filter = Filter::updateOrCreate(
                ['key' => $key],
                [
                    'translations' => ['en' => ['label' => $config['label']]],
                    'type' => 'select',
                    'show_on' => $config['show_on'] ?? ['home', 'listing'],
                    'order_index' => $order++,
                    'status' => true,
                ]
            );

            $valueOrder = 1;
            foreach ($config['values'] as $value => $label) {
                $translations = is_array($label)
                    ? collect($label)->map(fn ($text) => ['label' => $text])->all()
                    : ['en' => ['label' => $label]];
                FilterValue::updateOrCreate(
                    ['filter_id' => $filter->id, 'value' => $value],
                    [
                        'translations' => $translations,
                        'order_index' => $valueOrder++,
                        'status' => true,
                    ]
                );
            }
        }

        $rangeFilters = [
            'bedrooms' => 'Bedrooms',
            'bathrooms' => 'Bathrooms',
            'price' => 'Price Range',
            'sqft' => 'Sqft',
        ];

        foreach ($rangeFilters as $key => $label) {
            Filter::updateOrCreate(
                ['key' => $key],
                [
                    'translations' => ['en' => ['label' => $label]],
                    'type' => $key === 'bedrooms' || $key === 'bathrooms' ? 'select' : 'range',
                    'show_on' => ['home', 'listing'],
                    'order_index' => $order++,
                    'status' => true,
                ]
            );
        }

        // Bedrooms/bathrooms render as a select of common counts, editable later from the admin.
        foreach (['bedrooms', 'bathrooms'] as $key) {
            $filter = Filter::where('key', $key)->first();
            $valueOrder = 1;
            foreach (['1', '2', '3', '4', '5+'] as $count) {
                FilterValue::updateOrCreate(
                    ['filter_id' => $filter->id, 'value' => $count],
                    [
                        'translations' => ['en' => ['label' => $count]],
                        'order_index' => $valueOrder++,
                        'status' => true,
                    ]
                );
            }
        }
    }
}
