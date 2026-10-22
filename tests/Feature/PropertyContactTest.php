<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsAgencies;
use Tests\TestCase;

/** Property::displayContact() — the property page's contact card: the agent, else the agency. */
class PropertyContactTest extends TestCase
{
    use RefreshDatabase, BuildsAgencies;

    public function test_available_agent_is_shown(): void
    {
        $agency = $this->agency();
        $agent = $this->memberAgent($agency);
        $property = $this->property($agency, $agent);

        $this->assertSame($agent->id, $property->displayContact()->id);
    }

    public function test_falls_back_to_agency_when_agent_left_disabled_or_unapproved(): void
    {
        $agency = $this->agency();

        $left = $this->memberAgent($agency);
        $left->forceFill(['company_id' => null])->save();   // no longer in the agency
        $this->assertSame($agency->id, $this->property($agency, $left)->displayContact()->id);

        $disabled = $this->memberAgent($agency, attributes: ['is_active' => false]);
        $this->assertSame($agency->id, $this->property($agency, $disabled)->displayContact()->id);

        $pending = $this->memberAgent($agency, attributes: ['status' => 'pending']);
        $this->assertSame($agency->id, $this->property($agency, $pending)->displayContact()->id);

        // No agent assigned at all.
        $this->assertSame($agency->id, $this->property($agency)->displayContact()->id);
    }

    public function test_nobody_when_the_agency_is_unavailable_too(): void
    {
        $agency = $this->agency(['is_active' => false]);
        $agent = $this->memberAgent($agency, attributes: ['is_active' => false]);

        $this->assertNull($this->property($agency, $agent)->displayContact());
    }

    public function test_property_page_api_returns_the_agency_as_contact(): void
    {
        $agency = $this->agency(['company_name' => 'Skyline Realty']);
        $agent = $this->memberAgent($agency, attributes: ['is_active' => false]);
        $property = $this->property($agency, $agent);

        $this->getJson("/api/properties/{$property->slug}")->assertOk()
            ->assertJsonPath('contact.name', 'Skyline Realty')
            ->assertJsonPath('contact.type', 'company');
    }
}
