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
                    'sale' => 'Buy',
                    'rent' => 'Rent',
                ],
            ],
            'completion_status' => [
                'label' => 'Completion Status',
                'values' => [
                    'ready' => 'Ready',
                    'off_plan' => 'Off-Plan',
                ],
            ],
            'property_type' => [
                'label' => 'Property Type',
                'values' => [
                    'apartment' => 'Apartment',
                    'villa' => 'Villa',
                    'townhouse' => 'Townhouse',
                    'penthouse' => 'Penthouse',
                ],
            ],
            'location' => [
                'label' => 'Location',
                'values' => [],
            ],
        ];

        $order = 1;
        foreach ($selectFilters as $key => $config) {
            $filter = Filter::updateOrCreate(
                ['key' => $key],
                [
                    'translations' => ['en' => ['label' => $config['label']]],
                    'type' => 'select',
                    'show_on' => ['home', 'listing'],
                    'order_index' => $order++,
                    'status' => true,
                ]
            );

            $valueOrder = 1;
            foreach ($config['values'] as $value => $label) {
                FilterValue::updateOrCreate(
                    ['filter_id' => $filter->id, 'value' => $value],
                    [
                        'translations' => ['en' => ['label' => $label]],
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
