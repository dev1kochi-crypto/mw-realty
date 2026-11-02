<?php

namespace App\Exports;

use App\Imports\PropertiesImport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The listing import template: one column per PropertiesImport field (headings the mapping step
 * recognises by itself) and two example rows. Agencies can also upload their own export as-is —
 * the template is just the quickest start.
 */
class PropertiesImportTemplateExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    public function headings(): array
    {
        return array_map(fn ($f) => $f[0], PropertiesImport::FIELDS);
    }

    public function array(): array
    {
        $examples = [
            [
                'reference' => 'AG-1001', 'title' => '', 'listing_type' => 'Sale', 'property_type' => 'Apartment', 'price' => '1,850,000',
                'community' => 'Dubai Marina', 'sub_community' => 'Marina Gate 1', 'city' => 'Dubai', 'emirate' => 'Dubai', 'category' => 'Residential',
                'completion_status' => 'Ready', 'bedrooms' => '2', 'bathrooms' => '3', 'sqft' => '1250', 'furnished' => 'Unfurnished',
                'description' => '', 'key_features' => 'Full marina view; High floor; Vacant on transfer', 'amenities' => 'Pool, Gym, Covered Parking',
                'image_urls' => 'https://example.com/photo-1.jpg, https://example.com/photo-2.jpg', 'permit_number' => '7112345678',
                'permit_expires_at' => now()->addMonths(6)->toDateString(), 'latitude' => '25.0868', 'longitude' => '55.1475',
                'agent' => 'agent@example.com', 'view' => 'Marina', 'parking' => '1', 'developer' => 'Select Group', 'floor' => '32', 'year_built' => '2018',
            ],
            [
                'reference' => 'AG-1002', 'title' => 'Family Villa with Private Garden', 'listing_type' => 'Rent', 'property_type' => 'Villa', 'price' => '240k',
                'community' => 'Arabian Ranches', 'city' => 'Dubai', 'rental_period' => 'Yearly', 'bedrooms' => '4', 'bathrooms' => '5', 'sqm' => '420',
                'furnished' => 'No', 'description' => "Corner villa on a quiet street.\n\n- Private garden\n- Maid's room\n- Close to schools",
            ],
        ];

        return array_map(fn ($row) => array_map(fn ($field) => $row[$field] ?? '', array_keys(PropertiesImport::FIELDS)), $examples);
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
