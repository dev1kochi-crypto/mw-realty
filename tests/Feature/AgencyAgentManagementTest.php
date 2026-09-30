<?php

namespace Tests\Feature;

use App\Mail\AgencyMembershipMail;
use App\Models\AgencyAgent;
use App\Models\Lead;
use App\Models\PortalUser;
use App\Models\Property;
use App\Models\PropertyAssignmentHistory;
use App\Services\Agency\AgencyMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Concerns\BuildsAgencies;
use Tests\TestCase;

/** Registration, agency ⇄ agent membership, approval, isolation, and property ownership. */
class AgencyAgentManagementTest extends TestCase
{
    use RefreshDatabase, BuildsAgencies;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_management_pages_render(): void
    {
        $agency = $this->agency();
        $agent = $this->memberAgent($agency);
        $this->memberAgent($agency, AgencyAgent::PENDING);
        $this->property($agency, $agent);
        $membership = AgencyAgent::where('agent_id', $agent->id)->first();
        $invited = $this->independentAgent();
        app(AgencyMembershipService::class)->inviteExistingAgent($this->agency(), $invited);

        $this->signIn($agency);
        foreach (['active', 'pending', 'requests', 'history'] as $tab) {
            $this->get("/portal/agents?tab={$tab}")->assertOk();
        }
        $this->get("/portal/agents/{$membership->id}")->assertOk()->assertSee($agent->email);
        $this->get('/portal/agents/create')->assertOk()->assertSee('Invite an existing agent');
        $lead = $this->enquire($this->property($agency));
        $this->getJson("/portal/crm/leads/{$lead->id}")->assertOk()->assertJson(['can_assign' => true, 'owner_is_agency' => true]);

        $this->app['auth']->forgetGuards();
        $this->signIn($agent)->get('/portal/agency')->assertOk()->assertSee($agency->displayName());
        $this->app['auth']->forgetGuards();
        $this->signIn($invited)->get('/portal/agency')->assertOk()->assertSee('invited you');
        $this->getJson('/portal/agency/search?q=Agency')->assertOk()->assertJsonStructure(['results', 'pagination' => ['more']]);

        $this->app['auth']->forgetGuards();
        $this->signIn($this->superAdmin(), 'cms');
        foreach (['pending', 'active', 'suspended', 'awaiting', 'closed'] as $status) {
            $this->get("/admin/agency-agents?status={$status}")->assertOk();
        }

        $url = app(AgencyMembershipService::class)->setupUrl($agent);
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get($url)->assertOk()->assertSee('Set password');
    }

    // --- 1, 2: registration ---------------------------------------------------------------

    public function test_independent_agent_can_register(): void
    {
        $this->postJson('/portal/register', [
            'type' => 'agent', 'name' => 'John', 'email' => 'john@example.test',
            'password' => 'Password123', 'password_confirmation' => 'Password123',
        ])->assertOk()->assertJson(['otp_required' => true]);

        $john = PortalUser::where('email', 'john@example.test')->firstOrFail();
        $this->assertSame(PortalUser::ACCOUNT_INDIVIDUAL_AGENT, $john->accountType());
        $this->assertNull($john->company_id);
    }

    public function test_agency_can_register(): void
    {
        $this->postJson('/portal/register', [
            'type' => 'company', 'name' => 'Owner', 'company_name' => 'ABC Realty', 'email' => 'abc@example.test',
            'password' => 'Password123', 'password_confirmation' => 'Password123',
        ])->assertOk();

        $this->assertSame(PortalUser::ACCOUNT_AGENCY, PortalUser::where('email', 'abc@example.test')->firstOrFail()->accountType());
    }

    public function test_choosing_an_agency_at_signup_is_only_a_join_request(): void
    {
        $agency = $this->agency();
        $this->postJson('/portal/register', [
            'type' => 'agent', 'name' => 'Sam', 'email' => 'sam@example.test', 'company_id' => $agency->id,
            'password' => 'Password123', 'password_confirmation' => 'Password123',
        ])->assertOk();

        $sam = PortalUser::where('email', 'sam@example.test')->firstOrFail();
        $this->assertNull($sam->company_id, 'An agent must not be able to attach themselves to an agency.');
        $this->assertDatabaseHas('agency_agents', ['agency_id' => $agency->id, 'agent_id' => $sam->id, 'status' => AgencyAgent::REQUESTED]);
    }

    // --- 3, 4: agency adds agent, admin approval -------------------------------------------

    public function test_agency_added_agent_requires_admin_approval_before_becoming_active(): void
    {
        $agency = $this->agency();

        $this->signIn($agency)->post('/portal/agents', ['name' => 'New Agent', 'email' => 'new@example.test', 'phone' => '+971500000001'])
            ->assertRedirect(route('portal.agents.index', ['tab' => 'pending']));

        $agent = PortalUser::where('email', 'new@example.test')->firstOrFail();
        $membership = AgencyAgent::where('agent_id', $agent->id)->firstOrFail();
        $this->assertSame(AgencyAgent::PENDING, $membership->status);
        $this->assertTrue($membership->account_created_by_agency);
        $this->assertSame('pending', $agent->status);
        $this->assertNull($agent->company_id);
        $this->assertFalse($agency->hasEligibleAgent($agent->id), 'Pending agents are not assignable.');

        // Admin approves → active member, set-password email with a one-time link.
        $this->signIn($this->superAdmin(), 'cms')->post("/admin/agency-agents/{$membership->id}/approve")->assertRedirect();

        $agent->refresh();
        $this->assertSame(AgencyAgent::APPROVED, $membership->fresh()->status);
        $this->assertSame($agency->id, $agent->company_id);
        $this->assertSame('approved', $agent->status);
        $this->assertTrue($agency->hasEligibleAgent($agent->id));

        $setupUrl = null;
        Mail::assertQueued(AgencyMembershipMail::class, function (AgencyMembershipMail $mail) use ($agent, &$setupUrl) {
            if ($mail->hasTo($agent->email) && str_contains((string) $mail->actionUrl, 'agent-setup')) {
                $setupUrl = $mail->actionUrl;
                return true;
            }
            return false;
        });
        // The agency is emailed too, not just told in-app.
        Mail::assertQueued(AgencyMembershipMail::class, fn (AgencyMembershipMail $mail) => $mail->hasTo($agency->email) && $mail->subjectLine === 'Agent approved');

        $this->app['auth']->forgetGuards();
        $this->post($setupUrl, ['password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])->assertRedirect(route('portal.dashboard'));
        $this->assertAuthenticatedAs($agent->fresh(), 'portal');

        // The same link can't be replayed once the password is set (e.g. from another browser).
        $this->app['auth']->guard('portal')->logout();
        $this->flushSession();
        $this->post($setupUrl, ['password' => 'Other123x', 'password_confirmation' => 'Other123x'])->assertForbidden();
    }

    public function test_admin_can_reject_a_pending_agent(): void
    {
        $agency = $this->agency();
        $membership = app(AgencyMembershipService::class)->createAgentForAgency($agency, ['name' => 'X', 'email' => 'x@example.test']);

        $this->signIn($this->superAdmin(), 'cms')->post("/admin/agency-agents/{$membership->id}/reject", ['reason' => 'Unverified'])->assertRedirect();

        $this->assertSame(AgencyAgent::REJECTED, $membership->fresh()->status);
        $this->assertNull($membership->agent->fresh()->company_id);
        Mail::assertQueued(AgencyMembershipMail::class, fn (AgencyMembershipMail $mail) => $mail->hasTo($agency->email) && $mail->subjectLine === 'Agent not approved');
    }

    public function test_agency_cannot_exceed_its_plan_agent_limit(): void
    {
        $agency = $this->agency([], $this->plan(['agent_limit' => 1]));
        $this->memberAgent($agency);

        $this->signIn($agency)->post('/portal/agents', ['name' => 'Two', 'email' => 'two@example.test'])->assertSessionHasErrors('agent_limit');
        $this->assertDatabaseMissing('portal_users', ['email' => 'two@example.test']);
    }

    // --- 16: isolation of agents ----------------------------------------------------------

    public function test_agency_cannot_access_or_manage_another_agencys_agents(): void
    {
        $agencyA = $this->agency();
        $agencyB = $this->agency();
        $this->memberAgent($agencyB);
        $foreign = AgencyAgent::where('agency_id', $agencyB->id)->firstOrFail();

        $this->signIn($agencyA);
        $this->get("/portal/agents/{$foreign->id}")->assertNotFound();
        $this->post("/portal/agents/{$foreign->id}/suspend")->assertNotFound();
        $this->post("/portal/agents/{$foreign->id}/remove")->assertNotFound();
        $this->get('/portal/agents')->assertOk()->assertDontSee($foreign->agent->email);

        $this->assertSame(AgencyAgent::APPROVED, $foreign->fresh()->status);
    }

    // --- 19, 20: existing independent agent joins, no duplicate ---------------------------

    public function test_independent_agent_joins_agency_without_duplicate_account_and_keeps_data(): void
    {
        $john = $this->independentAgent(['email' => 'john@example.test', 'phone' => '+971501112222']);
        $ownProperty = $this->property($john, $john);
        $ownLead = Lead::create(['property_id' => $ownProperty->id, 'portal_user_id' => $john->id, 'agent_id' => $john->id, 'name' => 'Old buyer', 'status' => 'active']);
        $agency = $this->agency();
        $accounts = PortalUser::count();

        // Trying to "add" John as a new agent is refused and offers an invitation instead.
        $this->signIn($agency)->post('/portal/agents', ['name' => 'John', 'email' => 'someone-else@example.test', 'phone' => '+971501112222'])
            ->assertSessionHas('duplicateAgent', fn ($d) => $d['can_invite'] === true && $d['identifier'] === 'john@example.test');
        $this->assertSame($accounts, PortalUser::count());

        $this->post('/portal/agents/invite', ['identifier' => '+971501112222'])->assertRedirect();
        $invitation = AgencyAgent::where('agent_id', $john->id)->firstOrFail();
        $this->assertSame(AgencyAgent::INVITED, $invitation->status);

        $this->app['auth']->forgetGuards();
        $this->signIn($john)->post("/portal/agency/invitations/{$invitation->id}/accept")->assertRedirect();
        $this->assertSame(AgencyAgent::PENDING, $invitation->fresh()->status);

        $this->app['auth']->forgetGuards();
        $this->signIn($this->superAdmin(), 'cms')->post("/admin/agency-agents/{$invitation->id}/approve")->assertRedirect();

        $john->refresh();
        $this->assertSame($accounts, PortalUser::count(), 'No duplicate account is created.');
        $this->assertSame($agency->id, $john->company_id);
        $this->assertSame(PortalUser::ACCOUNT_AGENCY_AGENT, $john->accountType());

        // Personal listing is not silently moved into the agency; old lead still his.
        $this->assertSame($john->id, $ownProperty->fresh()->portal_user_id);
        $this->assertSame($john->id, $ownLead->fresh()->portal_user_id);
        $this->assertTrue(Lead::forOwner($john->id)->whereKey($ownLead->id)->exists());
    }

    public function test_agent_can_optionally_transfer_a_personal_listing_to_the_agency(): void
    {
        $agency = $this->agency();
        $agent = $this->memberAgent($agency);
        $listing = $this->property($agent, $agent);

        $this->signIn($agent)->post("/portal/agency/properties/{$listing->id}/transfer")->assertRedirect();

        $listing->refresh();
        $this->assertSame($agency->id, $listing->portal_user_id);
        $this->assertSame($agent->id, $listing->agent_id);
        $this->assertDatabaseHas('property_assignment_history', ['property_id' => $listing->id, 'action' => 'transferred_to_agency', 'to_agency_id' => $agency->id]);
    }

    // --- 21, 22: agent leaves -------------------------------------------------------------

    public function test_agent_leaving_keeps_account_and_agency_keeps_its_listings(): void
    {
        $agency = $this->agency();
        $agent = $this->memberAgent($agency);
        $agencyListing = $this->property($agency, $agent);
        $personalListing = $this->property($agent, $agent);

        $this->signIn($agent)->post('/portal/agency/leave')->assertRedirect();

        $membership = AgencyAgent::where('agent_id', $agent->id)->firstOrFail();
        $this->assertSame(AgencyAgent::INACTIVE, $membership->status);
        $this->assertNotNull($membership->left_at);
        $this->assertNotNull(PortalUser::find($agent->id), 'Account is not deleted.');
        $this->assertNull($agent->fresh()->company_id);

        $this->assertSame($agency->id, $agencyListing->fresh()->portal_user_id);
        $this->assertNull($agencyListing->fresh()->agent_id, 'Unassigned so new enquiries round-robin.');
        $this->assertSame($agent->id, $personalListing->fresh()->portal_user_id);
        $this->assertDatabaseHas('property_assignment_history', ['property_id' => $agencyListing->id, 'action' => 'agent_left', 'from_agent_id' => $agent->id]);
    }

    public function test_agency_can_remove_agent_and_hand_listings_to_another_agent(): void
    {
        $agency = $this->agency();
        $leaving = $this->memberAgent($agency);
        $taking = $this->memberAgent($agency);
        $listing = $this->property($agency, $leaving);
        $membership = AgencyAgent::where('agent_id', $leaving->id)->firstOrFail();

        $this->signIn($agency)->post("/portal/agents/{$membership->id}/remove", ['reassign_to' => $taking->id])->assertRedirect();

        $this->assertSame($taking->id, $listing->fresh()->agent_id);
    }

    // --- 5, 6, 7, 23 + validation: agency property ownership -------------------------------

    public function test_agency_can_create_property_with_an_agent(): void
    {
        $agency = $this->agency();
        $agent = $this->memberAgent($agency);

        $this->signIn($agency)->post('/portal/properties', $this->propertyPayload(['agent_id' => $agent->id]))->assertRedirect();

        $property = Property::latest('id')->firstOrFail();
        $this->assertSame($agency->id, $property->portal_user_id);
        $this->assertSame($agent->id, $property->agent_id);
        $this->assertSame('agency', $property->created_by_type);
        $this->assertSame($agency->id, $property->created_by_id);
    }

    public function test_agency_can_create_property_without_an_agent(): void
    {
        $agency = $this->agency();
        $this->memberAgent($agency);

        $this->signIn($agency)->post('/portal/properties', $this->propertyPayload())->assertRedirect();

        $property = Property::latest('id')->firstOrFail();
        $this->assertSame($agency->id, $property->portal_user_id);
        $this->assertNull($property->agent_id);
        // Saved normally without an agent; it goes live after the DLD permit review (ListingComplianceTest).
        $this->assertSame(Property::COMPLIANCE_DRAFT, $property->compliance_status);
        $this->assertFalse($property->status);
    }

    public function test_agency_with_zero_agents_can_create_and_publish_property(): void
    {
        $agency = $this->agency();

        $this->signIn($agency)->post('/portal/properties', $this->propertyPayload())->assertRedirect()->assertSessionHasNoErrors();
        $property = Property::where('portal_user_id', $agency->id)->whereNull('agent_id')->firstOrFail();

        // Publishing needs no agent — only the DLD permit review.
        $property->update(['compliance_status' => Property::COMPLIANCE_APPROVED]);
        $this->postJson("/portal/properties/{$property->id}/toggle-status")->assertOk();
        $this->assertTrue($property->fresh()->status);
    }

    public function test_agency_cannot_assign_an_agent_from_another_agency_or_an_inactive_one(): void
    {
        $agency = $this->agency();
        $foreign = $this->memberAgent($this->agency());
        $suspended = $this->memberAgent($agency, AgencyAgent::SUSPENDED);

        $this->signIn($agency);
        $this->post('/portal/properties', $this->propertyPayload(['agent_id' => $foreign->id]))->assertSessionHasErrors('agent_id');
        $this->post('/portal/properties', $this->propertyPayload(['agent_id' => $suspended->id]))->assertSessionHasErrors('agent_id');
        $this->assertDatabaseCount('properties', 0);
    }

    public function test_agency_can_reassign_a_property_to_another_agent(): void
    {
        $agency = $this->agency();
        $a = $this->memberAgent($agency);
        $b = $this->memberAgent($agency);
        $listing = $this->property($agency, $a);

        $this->signIn($agency)->put("/portal/properties/{$listing->id}", $this->propertyPayload(['agent_id' => $b->id, 'slug' => $listing->slug]))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($b->id, $listing->fresh()->agent_id);
        $this->assertTrue(PropertyAssignmentHistory::where('property_id', $listing->id)->where('action', 'agent_changed')
            ->where('from_agent_id', $a->id)->where('to_agent_id', $b->id)->exists());
    }

    // --- 17: property isolation -----------------------------------------------------------

    public function test_agency_cannot_access_another_agencys_properties(): void
    {
        $agencyA = $this->agency();
        $listing = $this->property($this->agency());

        $this->signIn($agencyA);
        $this->get("/portal/properties/{$listing->id}/edit")->assertNotFound();
        $this->put("/portal/properties/{$listing->id}", $this->propertyPayload(['slug' => $listing->slug]))->assertNotFound();
        $this->deleteJson("/portal/properties/{$listing->id}")->assertNotFound();
        $this->assertNotNull($listing->fresh());
    }

    public function test_agency_agent_sees_assigned_agency_listings_but_cannot_delete_them(): void
    {
        $agency = $this->agency();
        $agent = $this->memberAgent($agency);
        $mine = $this->property($agency, $agent);
        $colleagues = $this->property($agency, $this->memberAgent($agency));

        $this->signIn($agent);
        $this->get("/portal/properties/{$mine->id}/edit")->assertOk();
        $this->get("/portal/properties/{$colleagues->id}/edit")->assertNotFound();
        $this->deleteJson("/portal/properties/{$mine->id}")->assertNotFound();
        $this->assertNotNull($mine->fresh());
    }

    public function test_agency_agent_listing_belongs_to_agency_and_uses_agency_plan(): void
    {
        $agency = $this->agency([], $this->plan(['property_limit' => 1]));
        $agent = $this->memberAgent($agency);

        $this->signIn($agent)->post('/portal/properties', $this->propertyPayload())->assertRedirect();
        $this->assertDatabaseHas('properties', ['portal_user_id' => $agency->id, 'agent_id' => $agent->id, 'created_by_type' => 'agent', 'created_by_id' => $agent->id]);

        // The agency's limit (1) is now used up — for the agent too.
        $this->post('/portal/properties', $this->propertyPayload())->assertRedirect();
        $this->assertDatabaseCount('properties', 1);
    }
}
