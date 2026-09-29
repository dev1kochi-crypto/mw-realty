<?php

namespace Tests\Feature;

use App\Models\AgencyAgent;
use App\Models\Plan;
use App\Models\PlanUpgradeRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Concerns\BuildsAgencies;
use Tests\TestCase;

/** An agent in an agency works on the agency's plan; their own plan drops to Free, and Free again when they leave. */
class AgencyAgentPlanTest extends TestCase
{
    use RefreshDatabase, BuildsAgencies;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_joining_an_agency_switches_the_agent_onto_the_agency_plan(): void
    {
        $free = Plan::create(['translations' => ['en' => ['name' => 'Free']], 'price' => 0, 'billing_cycle' => 'free', 'status' => true, 'property_limit' => 3, 'order_index' => 0]);
        $ownPaid = $this->plan(['translations' => ['en' => ['name' => 'Agent Starter']], 'price' => 99, 'reports_access' => false, 'featured_per_month' => 0]);
        $agencyPlan = $this->plan(['translations' => ['en' => ['name' => 'Agency Pro']], 'price' => 499, 'reports_access' => true, 'featured_per_month' => 3]);

        $agency = $this->agency([], $agencyPlan);
        $john = $this->independentAgent(['plan_id' => $ownPaid->id, 'payment_status' => 'paid']);
        $request = PlanUpgradeRequest::create(['portal_user_id' => $john->id, 'plan_id' => $agencyPlan->id, 'status' => 'pending']);

        $this->assertFalse($john->hasReportsAccess());

        // Invited → accepts → admin approves.
        $this->signIn($agency)->post('/portal/agents/invite', ['identifier' => $john->email])->assertRedirect();
        $invitation = AgencyAgent::where('agent_id', $john->id)->firstOrFail();
        $this->app['auth']->forgetGuards();
        $this->signIn($john)->post("/portal/agency/invitations/{$invitation->id}/accept")->assertRedirect();
        $this->app['auth']->forgetGuards();
        $this->signIn($this->superAdmin(), 'cms')->post("/admin/agency-agents/{$invitation->id}/approve")->assertRedirect();

        $john->refresh();
        $this->assertTrue($john->isOnAgencyPlan());
        $this->assertSame($free->id, $john->plan_id, 'their own plan is dropped');
        $this->assertSame($agencyPlan->id, $john->effectivePlan()->id);
        $this->assertTrue($john->hasReportsAccess(), "reports come from the agency's plan");
        $this->assertSame(3, app(\App\Services\FeaturedListingService::class)->quota($john)['limit']);
        $this->assertSame('rejected', $request->fresh()->status, 'an open plan request is closed');

        // Admin list: agency plan, covered by agency.
        $this->get(route('cms.portal-accounts.index', ['type' => 'agent']), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('Agency plan')->assertSee('Covered by agency');

        // Portal: no upgrade button, no buying.
        $this->app['auth']->forgetGuards();
        $this->signIn($john->fresh());
        $this->get('/portal/plans')->assertOk()->assertSee('Covered by your agency')->assertSee('Agency Pro')->assertDontSee('btn-portal-upgrade-cta', false);
        $this->post('/portal/plans/request', ['plan_id' => $agencyPlan->id])->assertSessionHasErrors('plan_id');

        // Leaves → independent on Free.
        $this->post('/portal/agency/leave')->assertRedirect();
        $john->refresh();
        $this->assertFalse($john->isOnAgencyPlan());
        $this->assertSame($free->id, $john->effectivePlan()->id);
        $this->assertFalse($john->hasReportsAccess());
    }

    public function test_any_accepted_connection_switches_plans_but_a_brokerage_name_does_not(): void
    {
        $free = Plan::create(['translations' => ['en' => ['name' => 'Free']], 'price' => 0, 'billing_cycle' => 'free', 'status' => true, 'property_limit' => 3, 'order_index' => 0]);
        $ownPlan = $this->plan(['translations' => ['en' => ['name' => 'Agent Starter']], 'price' => 99]);
        $agency = $this->agency([], $this->plan(['translations' => ['en' => ['name' => 'Agency Pro']], 'price' => 499]));

        // Naming a brokerage in the profile is KYC text only — no link, no plan change.
        $maya = $this->independentAgent(['plan_id' => $ownPlan->id]);
        $this->signIn($maya)->postJson('/portal/profile', ['section' => 'agent', 'affiliated_brokerage' => $agency->company_name])->assertOk();
        $maya->refresh();
        $this->assertSame($agency->company_name, $maya->affiliated_brokerage);
        $this->assertNull($maya->company_id);
        $this->assertSame($ownPlan->id, $maya->effectivePlan()->id);

        // The agent asks to join (agency accepts, admin approves): own plan dropped, agency's plan applies.
        $sam = $this->independentAgent(['plan_id' => $ownPlan->id]);
        $this->signIn($sam)->get('/portal/agency')->assertOk()->assertSee('no refund', false);
        $membership = app(\App\Services\Agency\AgencyMembershipService::class)->requestToJoin($sam, $agency);
        $this->app['auth']->forgetGuards();
        $this->signIn($agency)->get('/portal/agents?tab=requests')->assertOk()->assertSee('Join requests waiting for you')
            ->assertSee('moves to your plan on approval');
        app(\App\Services\Agency\AgencyMembershipService::class)->respondToJoinRequest($agency, $membership, true);
        $this->get('/portal/agents')->assertOk()->assertDontSee('Join requests waiting for you');
        $this->app['auth']->forgetGuards();
        $this->signIn($this->superAdmin(), 'cms')->post("/admin/agency-agents/{$membership->id}/approve")->assertRedirect();
        $sam->refresh();
        $this->assertSame($agency->id, $sam->company_id);
        $this->assertTrue($sam->isOnAgencyPlan());
        $this->assertSame($free->id, $sam->plan_id, 'own plan dropped');
        $this->assertSame(499.0, (float) $sam->effectivePlan()->price);
        $this->assertSame($ownPlan->id, $membership->fresh()->previous_plan_id, 'the dropped plan is recorded');
        $this->app['auth']->forgetGuards();
        $this->signIn($agency)->get('/portal/agents')->assertOk()->assertSee('switched from Agent Starter');
        $this->app['auth']->forgetGuards();
        $this->signIn($this->superAdmin(), 'cms');

        // An agent the agency created is always covered.
        $createdMembership = app(\App\Services\Agency\AgencyMembershipService::class)->createAgentForAgency($agency, ['name' => 'New One', 'email' => 'new-one@example.test']);
        $this->post("/admin/agency-agents/{$createdMembership->id}/approve")->assertRedirect();
        $this->assertTrue($createdMembership->agent->fresh()->isOnAgencyPlan());
        $this->assertSame(499.0, (float) $createdMembership->agent->fresh()->effectivePlan()->price);
    }
}
