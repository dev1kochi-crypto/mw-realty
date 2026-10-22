<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsAgencies;
use Tests\TestCase;

/** Portal Properties list: agent shown on each card, and the agent / type / status / premium filters. */
class PropertyListFiltersTest extends TestCase
{
    use RefreshDatabase, BuildsAgencies;

    public function test_agency_sees_who_works_each_listing_and_can_filter(): void
    {
        $agency = $this->agency();
        $alice = $this->memberAgent($agency, attributes: ['name' => 'Alice Member']);
        $withAgent = $this->property($agency, $alice, ['listing_type' => 'sale']);
        $noAgent = $this->property($agency, null, ['listing_type' => 'rent', 'featured' => true]);
        $inactive = $this->property($agency, $alice, ['listing_type' => 'sale', 'status' => false]);
        $card = fn ($p) => 'portal-property-col" data-id="' . $p->id . '"';

        $this->signIn($agency);
        $this->get('/portal/properties')->assertOk()
            ->assertSee('Alice Member')->assertSee('Agency listing')
            ->assertSee($card($withAgent), false)->assertSee($card($noAgent), false);

        $this->get('/portal/properties?agent=none')->assertOk()
            ->assertSee($card($noAgent), false)->assertDontSee($card($withAgent), false)->assertSee('Clear filters');
        $this->get("/portal/properties?agent={$alice->id}")->assertOk()
            ->assertSee($card($withAgent), false)->assertSee($card($inactive), false)->assertDontSee($card($noAgent), false);
        $this->get('/portal/properties?listing=rent')->assertOk()
            ->assertSee($card($noAgent), false)->assertDontSee($card($withAgent), false);
        $this->get('/portal/properties?status=inactive')->assertOk()
            ->assertSee($card($inactive), false)->assertDontSee($card($withAgent), false);
        $this->get('/portal/properties?premium=1')->assertOk()
            ->assertSee($card($noAgent), false)->assertDontSee($card($withAgent), false);

        // Agent picker: the agency's own agents only, searched on the server.
        $other = $this->memberAgent($this->agency(), attributes: ['name' => 'Bob Elsewhere']);
        $this->getJson('/portal/properties/agent-options?q=')->assertOk()
            ->assertJsonFragment(['text' => 'Alice Member'])->assertJsonMissing(['text' => 'Bob Elsewhere']);
    }

    public function test_an_agent_has_no_agent_filter(): void
    {
        $agent = $this->independentAgent();
        $this->signIn($agent)->get('/portal/properties')->assertOk()->assertDontSee('id="pfAgentToggle"', false)->assertSee('name="listing"', false);
        $this->getJson('/portal/properties/agent-options')->assertForbidden();
    }
}
