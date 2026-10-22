<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsAgencies;
use Tests\TestCase;

/** "Open house properties" filter and "Book a viewing" on the property page. */
class OpenHouseAndViewingTest extends TestCase
{
    use RefreshDatabase, BuildsAgencies;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.recaptcha.secret_key' => null]); // no live reCAPTCHA in tests
    }

    private function listing(string $title, ?array $dates): Property
    {
        return Property::create([
            'slug' => uniqid('oh-'), 'reference_no' => uniqid('P'), 'status' => true,
            'translations' => ['en' => ['title' => $title, 'city' => 'Dubai']],
            'segment' => 'residential', 'listing_type' => 'sale', 'price' => 1000000,
            'latitude' => 25.2, 'longitude' => 55.27, 'available_dates' => $dates,
        ]);
    }

    public function test_open_house_filter_keeps_listings_with_an_upcoming_day(): void
    {
        $this->listing('Upcoming Open House', [today()->subDays(3)->toDateString(), today()->addDays(4)->toDateString()]);
        $this->listing('Open House Today', [today()->toDateString()]);
        $this->listing('Past Open House', [today()->subDays(2)->toDateString()]);
        $this->listing('Available Immediately', null);

        $names = fn (string $q) => collect($this->getJson('/api/properties/map' . $q)->assertOk()->json('markers'))->pluck('name')->sort()->values()->all();

        $this->assertCount(4, $names(''));
        $this->assertSame(['Open House Today', 'Upcoming Open House'], $names('?open_house=1'));
    }

    public function test_last_open_house_date_follows_the_dates(): void
    {
        $p = $this->listing('X', ['2026-12-01', '2026-11-05']);
        $this->assertSame('2026-12-01', $p->fresh()->last_open_house_date->toDateString());

        $p->update(['available_dates' => null]);
        $this->assertNull($p->fresh()->last_open_house_date);
    }

    public function test_booking_a_viewing_creates_a_lead_for_the_listing_owner(): void
    {
        $agency = $this->agency();
        $property = $this->property($agency, null, ['available_dates' => null]);
        $day = today()->addDays(2)->toDateString();

        $this->postJson('/leads/viewing', [
            'property_id' => $property->id, 'name' => 'Sara Buyer', 'email' => 'sara@example.test',
            'phone' => '501234567', 'phone_country_code' => '+971',
            'viewing_date' => $day, 'viewing_time' => 'afternoon', 'note' => 'Bringing my family',
        ])->assertOk()->assertJsonFragment(['when' => \Illuminate\Support\Carbon::parse($day)->format('l, j M Y') . ', Afternoon (12 PM – 4 PM)']);

        $lead = Lead::where('email', 'sara@example.test')->firstOrFail();
        $this->assertSame($property->id, $lead->property_id);
        $this->assertSame($agency->id, $lead->portal_user_id);
        $this->assertSame('book-viewing', $lead->page_source);
        $this->assertStringContainsString('Viewing request', $lead->message);
        $this->assertStringContainsString('Bringing my family', $lead->message);
        $this->assertSame(['viewing_date' => $day, 'viewing_time' => 'afternoon'], array_intersect_key($lead->extra_fields, array_flip(['viewing_date', 'viewing_time'])));
    }

    public function test_viewing_day_must_be_an_open_house_day_when_the_listing_has_them(): void
    {
        $property = $this->property($this->agency(), null, ['available_dates' => [today()->addDays(5)->toDateString()]]);
        $payload = ['property_id' => $property->id, 'name' => 'Sam', 'email' => 'sam@example.test', 'viewing_time' => 'morning'];

        $this->postJson('/leads/viewing', $payload + ['viewing_date' => today()->addDay()->toDateString()])->assertJsonValidationErrors('viewing_date');
        $this->postJson('/leads/viewing', $payload + ['viewing_date' => today()->subDay()->toDateString()])->assertJsonValidationErrors('viewing_date');
        $this->postJson('/leads/viewing', array_merge($payload, ['viewing_date' => today()->addDays(5)->toDateString(), 'viewing_time' => 'midnight']))->assertJsonValidationErrors('viewing_time');
        $this->postJson('/leads/viewing', $payload + ['viewing_date' => today()->addDays(5)->toDateString()])->assertOk();
    }
}
