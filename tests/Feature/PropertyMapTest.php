<?php

namespace Tests\Feature;

use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** GET /api/properties/map — pins for the listing pages' map view. */
class PropertyMapTest extends TestCase
{
    use RefreshDatabase;

    private function listing(array $attributes): Property
    {
        return Property::create(array_merge([
            'slug' => uniqid('map-'), 'reference_no' => uniqid('P'), 'status' => true,
            'translations' => ['en' => ['title' => 'Listing', 'city' => 'Dubai']],
            'segment' => 'residential', 'listing_type' => 'sale', 'price' => 1000000,
            'latitude' => 25.2, 'longitude' => 55.27,
        ], $attributes));
    }

    public function test_returns_pins_with_listing_filters_and_segment(): void
    {
        $this->listing(['listing_type' => 'sale', 'translations' => ['en' => ['title' => 'Marina Sale']]]);
        $this->listing(['listing_type' => 'rent', 'translations' => ['en' => ['title' => 'Marina Rent']]]);
        $this->listing(['segment' => 'commercial', 'translations' => ['en' => ['title' => 'Office Tower']]]);
        $this->listing(['latitude' => null, 'longitude' => null, 'translations' => ['en' => ['title' => 'No Coordinates']]]);
        $this->listing(['status' => false, 'translations' => ['en' => ['title' => 'Inactive']]]);

        $all = $this->getJson('/api/properties/map')->assertOk()->json();
        $this->assertSame(['Marina Sale', 'Marina Rent'], collect($all['markers'])->pluck('name')->sort()->reverse()->values()->all());
        $this->assertSame(2, $all['total']);
        $this->assertEqualsWithDelta(25.2, $all['bounds']['south'], 0.0001);
        $this->assertSame(['id', 'slug', 'lat', 'lng'], array_slice(array_keys($all['markers'][0]), 0, 4));

        $rent = $this->getJson('/api/properties/map?listing_type=rent')->json();
        $this->assertSame(['Marina Rent'], collect($rent['markers'])->pluck('name')->all());

        $commercial = $this->getJson('/api/properties/map?segment=commercial')->json();
        $this->assertSame(['Office Tower'], collect($commercial['markers'])->pluck('name')->all());
    }

    public function test_bounding_box_limits_pins_but_not_the_total(): void
    {
        $this->listing(['latitude' => 25.2, 'longitude' => 55.27]);   // Dubai
        $this->listing(['latitude' => 24.45, 'longitude' => 54.38]);  // Abu Dhabi

        $dubai = $this->getJson('/api/properties/map?south=25&west=55&north=25.4&east=55.5')->assertOk()->json();
        $this->assertCount(1, $dubai['markers']);
        $this->assertSame(1, $dubai['in_view']);
        $this->assertSame(2, $dubai['total']);

        // An invalid box is ignored rather than erroring.
        $this->getJson('/api/properties/map?south=abc&west=55&north=25&east=56')->assertOk()->assertJsonCount(2, 'markers');
        $this->getJson('/api/properties/map?south=26&west=55&north=25&east=56')->assertOk()->assertJsonCount(2, 'markers');
    }

    public function test_map_pages_are_served(): void
    {
        foreach (['/properties/map', '/commercial/map', '/premium-properties/map'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
