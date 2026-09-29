<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsAgencies;
use Tests\TestCase;

/** Mark as sold / rented: links the buyer lead, closes it as won, takes the listing off the website. */
class PropertySoldTest extends TestCase
{
    use RefreshDatabase, BuildsAgencies;

    public function test_marking_sold_to_an_existing_lead_closes_it_and_hides_the_listing(): void
    {
        $agency = $this->agency([], $this->plan(['reports_access' => true]));
        $agent = $this->memberAgent($agency, attributes: ['name' => 'Alice Member']);
        LeadStage::seedDefaultsFor($agency);
        $property = $this->property($agency, $agent, ['price' => 2000000, 'listing_type' => 'sale', 'translations' => ['en' => ['title' => 'Palm Villa']]]);
        $lead = $this->enquire($property, 'Happy Buyer');

        $this->signIn($agency);
        $this->get('/portal/properties')->assertSee('portal-property-col" data-id="' . $property->id . '"', false)->assertDontSee(route('portal.sold.index'));

        // The buyer picker lists this listing's enquirer first.
        $this->getJson("/portal/properties/{$property->id}/sale-leads")->assertOk()
            ->assertJsonPath('results.0.id', $lead->id)->assertJsonPath('results.0.this_property', true);

        $this->postJson("/portal/properties/{$property->id}/mark-sold", [
            'type' => 'sold', 'price' => 1850000, 'sold_at' => now()->subDay()->toDateString(), 'commission' => 37000,
            'notes' => 'Cash buyer', 'buyer_mode' => 'existing', 'lead_id' => $lead->id,
        ])->assertOk()->assertJsonPath('success', true);

        $property->refresh();
        $this->assertTrue($property->isSold());
        $this->assertFalse($property->status, 'a sold listing is off the website');
        $this->assertSame($lead->id, $property->sold_lead_id);
        $this->assertEquals(1850000, $property->sold_price);

        $lead->refresh();
        $this->assertSame('Closed Won', $lead->stage->name);
        $this->assertSame(now()->subDay()->toDateString(), $lead->closed_at->toDateString());
        $this->assertTrue($lead->notesHistory()->where('type', 'sale')->where('body', 'like', '%Palm Villa%')->exists());

        // Website, portal lists, sold menu, lead profile, sales report.
        $this->getJson("/api/properties/{$property->slug}")->assertNotFound();
        $this->get('/portal/properties')->assertOk()->assertSee(route('portal.sold.index'))->assertDontSee('portal-property-col" data-id="' . $property->id . '"', false);
        $this->get('/portal/sold-listings')->assertOk()->assertSee('Palm Villa')->assertSee('Happy Buyer')->assertSee('AED 1,850,000');
        $this->get("/portal/crm/leads/{$lead->id}")->assertOk()->assertSee('Purchases')->assertSee('Palm Villa');
        $this->assertEquals(1850000, app(\App\Services\Crm\PortalReportService::class)->sales($agency, 30)['kpis']['won_value'], 'the report uses the sold price');

        // Can't be switched back on while sold; reverting restores it.
        $this->postJson("/portal/properties/{$property->id}/toggle-status")->assertStatus(422);
        $this->postJson("/portal/properties/{$property->id}/mark-sold", ['type' => 'sold', 'price' => 1, 'sold_at' => now()->toDateString(), 'buyer_mode' => 'existing', 'lead_id' => $lead->id])->assertStatus(422);
        $this->postJson("/portal/properties/{$property->id}/revert-sold")->assertOk();
        $property->refresh();
        $this->assertFalse($property->isSold());
        $this->assertTrue($property->status);
        $this->getJson("/api/properties/{$property->slug}")->assertOk();
    }

    public function test_renting_to_a_new_buyer_creates_the_lead(): void
    {
        $agent = $this->independentAgent();
        $property = $this->property($agent, $agent, ['price' => 120000, 'listing_type' => 'rent']);

        $this->signIn($agent);
        $this->postJson("/portal/properties/{$property->id}/mark-sold", [
            'type' => 'rented', 'price' => 115000, 'sold_at' => now()->toDateString(), 'rented_until' => now()->addYear()->toDateString(),
            'buyer_mode' => 'new', 'buyer' => ['name' => 'New Tenant', 'email' => 'tenant@example.test'],
        ])->assertOk();

        $lead = Lead::where('email', 'tenant@example.test')->firstOrFail();
        $this->assertSame($agent->id, $lead->portal_user_id);
        $this->assertSame($property->id, $lead->property_id);
        $this->assertTrue($lead->stage->isWon(), 'a won stage is created when the owner has none');
        $this->assertSame(Property::RENTED, $property->fresh()->sold_type);
    }

    public function test_picker_lists_every_crm_lead_not_just_the_listing_owners(): void
    {
        $agency = $this->agency();
        LeadStage::seedDefaultsFor($agency);
        $lead = $this->enquire($this->property($agency, null), 'Olga Petrova');
        $houseListing = $this->property(null, null, ['price' => 500000]);

        $this->signIn($this->superAdmin(), 'cms');
        $this->getJson("/portal/properties/{$houseListing->id}/sale-leads")->assertOk()->assertJsonPath('results.0.name', 'Olga Petrova');
        $this->getJson("/portal/properties/{$houseListing->id}/sale-leads?q=olga")->assertOk()->assertJsonCount(1, 'results');

        $this->postJson("/portal/properties/{$houseListing->id}/mark-sold", [
            'type' => 'sold', 'price' => 480000, 'sold_at' => now()->toDateString(), 'buyer_mode' => 'existing', 'lead_id' => $lead->id,
        ])->assertOk();
        $this->assertSame('Closed Won', $lead->fresh()->stage->name, "uses the lead owner's won stage");
    }

    public function test_cannot_mark_another_accounts_listing_or_link_its_leads(): void
    {
        $mine = $this->independentAgent();
        $other = $this->independentAgent();
        $theirs = $this->property($other, $other);
        $myListing = $this->property($mine, $mine);
        $theirLead = $this->enquire($theirs, 'Their Buyer');

        $this->signIn($mine);
        $payload = ['type' => 'sold', 'price' => 1, 'sold_at' => now()->toDateString(), 'buyer_mode' => 'existing', 'lead_id' => $theirLead->id];
        $this->postJson("/portal/properties/{$theirs->id}/mark-sold", $payload)->assertNotFound();
        $this->postJson("/portal/properties/{$myListing->id}/mark-sold", $payload)->assertNotFound();
        $this->assertFalse($myListing->fresh()->isSold());
        $this->postJson("/portal/properties/{$myListing->id}/mark-sold", array_merge($payload, ['buyer_mode' => 'new', 'buyer' => ['name' => 'X']]))->assertStatus(422);
    }
}
