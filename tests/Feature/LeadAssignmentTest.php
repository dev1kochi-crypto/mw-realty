<?php

namespace Tests\Feature;

use App\Models\AgencyAgent;
use App\Models\Lead;
use App\Models\LeadAssignmentHistory;
use App\Services\Agency\AgencyMembershipService;
use App\Services\Agency\AssignmentActor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Concerns\BuildsAgencies;
use Tests\TestCase;

/** Enquiry (lead) assignment: property agent, round-robin, agency-unassigned, manual, history, visibility. */
class LeadAssignmentTest extends TestCase
{
    use RefreshDatabase, BuildsAgencies;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    // --- 8 ---------------------------------------------------------------------------------

    public function test_property_with_assigned_agent_sends_enquiry_directly_to_that_agent(): void
    {
        $agency = $this->agency();
        $this->memberAgent($agency);
        $agent = $this->memberAgent($agency);
        $lead = $this->enquire($this->property($agency, $agent));

        $this->assertSame($agency->id, $lead->portal_user_id);
        $this->assertSame($agent->id, $lead->agent_id);
        $this->assertSame(Lead::ASSIGN_PROPERTY_AGENT, $lead->assignment_type);
        $this->assertDatabaseHas('lead_assignment_history', ['lead_id' => $lead->id, 'agent_id' => $agent->id, 'assignment_type' => 'property_agent']);

        // Both the agency and the agent can see it.
        $this->assertTrue(Lead::forOwner($agency->id)->whereKey($lead->id)->exists());
        $this->assertTrue(Lead::forOwner($agent->id)->whereKey($lead->id)->exists());
    }

    public function test_independent_agent_property_enquiry_goes_to_the_agent(): void
    {
        $agent = $this->independentAgent();
        $lead = $this->enquire($this->property($agent, $agent));

        $this->assertSame($agent->id, $lead->portal_user_id);
        $this->assertSame($agent->id, $lead->agent_id);
    }

    // --- 9, 11 ------------------------------------------------------------------------------

    public function test_property_without_agent_round_robins_by_enquiry_not_by_property(): void
    {
        $agency = $this->agency();
        [$a, $b, $c] = [$this->memberAgent($agency), $this->memberAgent($agency), $this->memberAgent($agency)];
        $properties = [$this->property($agency), $this->property($agency), $this->property($agency)];

        $assigned = [];
        foreach ([0, 0, 1, 2, 0, 1] as $i) {
            $lead = $this->enquire($properties[$i]);
            $this->assertSame(Lead::ASSIGN_ROUND_ROBIN, $lead->assignment_type);
            $assigned[] = $lead->agent_id;
        }

        $this->assertSame([$a->id, $b->id, $c->id, $a->id, $b->id, $c->id], $assigned);
        $this->assertSame($c->id, $agency->leadAssignmentSetting()->first()->last_agent_id);
        foreach ($properties as $property) {
            $this->assertNull($property->fresh()->agent_id, 'Round-robin never assigns the property itself.');
        }
    }

    // --- 10 --------------------------------------------------------------------------------

    public function test_agency_with_no_active_agents_gets_an_unassigned_agency_enquiry(): void
    {
        $agency = $this->agency();
        $lead = $this->enquire($this->property($agency));

        $this->assertSame($agency->id, $lead->portal_user_id);
        $this->assertNull($lead->agent_id);
        $this->assertSame(Lead::ASSIGN_AGENCY_UNASSIGNED, $lead->assignment_type);
        $this->assertTrue(Lead::forOwner($agency->id)->whereKey($lead->id)->exists(), 'The agency sees it.');
    }

    // --- 12, 13, 14 ------------------------------------------------------------------------

    public function test_pending_suspended_inactive_and_foreign_agents_are_skipped(): void
    {
        $agency = $this->agency();
        $a = $this->memberAgent($agency);
        $suspended = $this->memberAgent($agency, AgencyAgent::SUSPENDED);
        $pending = $this->memberAgent($agency, AgencyAgent::PENDING);
        $disabled = $this->memberAgent($agency, AgencyAgent::APPROVED, ['is_active' => false]);
        $unapproved = $this->memberAgent($agency, AgencyAgent::APPROVED, ['status' => 'pending']);
        $this->memberAgent($this->agency()); // another agency's agent
        $c = $this->memberAgent($agency);
        $property = $this->property($agency);

        $assigned = collect(range(1, 4))->map(fn () => $this->enquire($property)->agent_id)->all();

        $this->assertSame([$a->id, $c->id, $a->id, $c->id], $assigned);
        foreach ([$suspended, $pending, $disabled, $unapproved] as $skipped) {
            $this->assertSame(0, Lead::where('agent_id', $skipped->id)->count());
        }
    }

    public function test_agent_becoming_inactive_mid_rotation_is_skipped_from_then_on(): void
    {
        $agency = $this->agency();
        [$a, $b, $c] = [$this->memberAgent($agency), $this->memberAgent($agency), $this->memberAgent($agency)];
        $property = $this->property($agency);

        foreach (range(1, 6) as $_) {
            $this->enquire($property);
        }
        app(AgencyMembershipService::class)->suspend(AgencyAgent::where('agent_id', $b->id)->first(), 'agency');

        $next = collect(range(7, 10))->map(fn () => $this->enquire($property)->agent_id)->all();
        $this->assertSame([$a->id, $c->id, $a->id, $c->id], $next);
    }

    // --- 27, 28 ----------------------------------------------------------------------------

    public function test_agents_approved_later_receive_new_enquiries_without_editing_the_property(): void
    {
        $agency = $this->agency();
        $property = $this->property($agency);
        $first = $this->enquire($property);
        $this->assertNull($first->agent_id);

        $a = $this->memberAgent($agency);
        $second = $this->enquire($property);

        $this->assertSame($a->id, $second->agent_id);
        $this->assertNull($first->fresh()->agent_id, 'Existing unassigned enquiries are not moved automatically.');
    }

    public function test_assigning_an_agent_later_routes_new_enquiries_to_them_and_keeps_old_ones(): void
    {
        $agency = $this->agency();
        $a = $this->memberAgent($agency);
        $b = $this->memberAgent($agency);
        $property = $this->property($agency);

        $old = $this->enquire($property); // round-robin → A
        $property->update(['agent_id' => $b->id]);
        $new = collect(range(1, 3))->map(fn () => $this->enquire($property));

        $this->assertSame($a->id, $old->fresh()->agent_id);
        $this->assertTrue($new->every(fn ($lead) => $lead->agent_id === $b->id && $lead->assignment_type === Lead::ASSIGN_PROPERTY_AGENT));
    }

    public function test_property_still_pointing_at_a_departed_agent_falls_back_to_round_robin(): void
    {
        $agency = $this->agency();
        $gone = $this->memberAgent($agency);
        $stays = $this->memberAgent($agency);
        $property = $this->property($agency, $gone);
        app(AgencyMembershipService::class)->suspend(AgencyAgent::where('agent_id', $gone->id)->first(), 'agency');

        $lead = $this->enquire($property);

        $this->assertSame($stays->id, $lead->agent_id);
        $this->assertSame(Lead::ASSIGN_ROUND_ROBIN, $lead->assignment_type);
    }

    // --- 24, 25 ----------------------------------------------------------------------------

    public function test_agency_can_manually_reassign_and_history_is_preserved(): void
    {
        $agency = $this->agency();
        $a = $this->memberAgent($agency);
        $b = $this->memberAgent($agency);
        $lead = $this->enquire($this->property($agency)); // → A

        $this->signIn($agency)->postJson("/portal/crm/leads/{$lead->id}/assign", ['agent_id' => $b->id])->assertOk();

        $lead->refresh();
        $this->assertSame($b->id, $lead->agent_id);
        $this->assertSame(Lead::ASSIGN_REASSIGNED, $lead->assignment_type);
        $history = LeadAssignmentHistory::where('lead_id', $lead->id)->orderBy('id')->get();
        $this->assertSame(['round_robin', 'reassigned'], $history->pluck('assignment_type')->all());
        $this->assertSame([$a->id, $b->id], $history->pluck('agent_id')->all());
        $this->assertSame($a->id, $history[1]->previous_agent_id);
        $this->assertSame('agency', $history[1]->assigned_by_type);
    }

    public function test_unassigned_lead_manual_assignment_and_reassign_validation(): void
    {
        $agency = $this->agency();
        $lead = $this->enquire($this->property($agency)); // no agents → unassigned
        $a = $this->memberAgent($agency);
        $foreign = $this->memberAgent($this->agency());

        $this->signIn($agency);
        $this->postJson("/portal/crm/leads/{$lead->id}/assign", ['agent_id' => $foreign->id])->assertUnprocessable();
        $this->postJson("/portal/crm/leads/{$lead->id}/assign", ['agent_id' => $a->id])->assertOk();

        $this->assertSame(Lead::ASSIGN_MANUAL, $lead->fresh()->assignment_type);
    }

    public function test_agent_leaving_keeps_historical_assignment_but_loses_sight_of_agency_lead(): void
    {
        $agency = $this->agency();
        $b = $this->memberAgent($agency);
        $lead = $this->enquire($this->property($agency, $b));
        $this->assertTrue(Lead::forOwner($b->id)->whereKey($lead->id)->exists());

        app(AgencyMembershipService::class)->endMembership(AgencyAgent::where('agent_id', $b->id)->first(), AssignmentActor::portal($agency));

        $this->assertSame($b->id, $lead->fresh()->agent_id, 'Originally assigned to B — not changed automatically.');
        $this->assertSame(1, LeadAssignmentHistory::where('lead_id', $lead->id)->count());
        $this->assertFalse(Lead::forOwner($b->fresh()->id)->whereKey($lead->id)->exists());
        $this->assertTrue(Lead::forOwner($agency->id)->whereKey($lead->id)->exists());
    }

    public function test_distribute_unassigned_leads_on_request(): void
    {
        $agency = $this->agency();
        $property = $this->property($agency);
        $leads = collect(range(1, 3))->map(fn () => $this->enquire($property));
        $a = $this->memberAgent($agency);
        $b = $this->memberAgent($agency);

        $this->signIn($agency)->post('/portal/crm/leads-distribute')->assertRedirect();

        $this->assertSame([$a->id, $b->id, $a->id], $leads->map(fn ($lead) => $lead->fresh()->agent_id)->all());
    }

    // --- 18 + agent-level visibility -------------------------------------------------------

    public function test_agency_cannot_see_or_touch_another_agencys_enquiries(): void
    {
        $agencyA = $this->agency();
        $agencyB = $this->agency();
        $foreignLead = $this->enquire($this->property($agencyB));

        $this->signIn($agencyA);
        $this->getJson("/portal/crm/leads/{$foreignLead->id}")->assertNotFound();
        $this->postJson("/portal/crm/leads/{$foreignLead->id}/assign", ['agent_id' => null])->assertNotFound();
        $this->deleteJson("/portal/crm/leads/{$foreignLead->id}")->assertNotFound();
        $this->get('/portal/crm/leads')->assertOk()->assertDontSee('buyer@example.test');
        $this->assertNotNull($foreignLead->fresh());
    }

    public function test_agency_agent_only_sees_enquiries_assigned_to_them_and_cannot_delete_or_reassign(): void
    {
        $agency = $this->agency();
        $a = $this->memberAgent($agency);
        $b = $this->memberAgent($agency);
        $mine = $this->enquire($this->property($agency, $a));
        $theirs = $this->enquire($this->property($agency, $b));

        $this->signIn($a);
        $this->getJson("/portal/crm/leads/{$mine->id}")->assertOk()->assertJson(['can_assign' => false]);
        $this->getJson("/portal/crm/leads/{$theirs->id}")->assertNotFound();
        $this->postJson("/portal/crm/leads/{$mine->id}/assign", ['agent_id' => $a->id])->assertNotFound();
        $this->deleteJson("/portal/crm/leads/{$mine->id}")->assertNotFound();
        $this->assertNotNull($mine->fresh());
    }

    public function test_admin_owner_transfer_resets_agent_and_records_history(): void
    {
        $agencyA = $this->agency();
        $agencyB = $this->agency();
        $a = $this->memberAgent($agencyA);
        $lead = $this->enquire($this->property($agencyA, $a));

        app(\App\Services\Crm\LeadService::class)->assignOwner($lead, $agencyB->id);

        $lead->refresh();
        $this->assertSame($agencyB->id, $lead->portal_user_id);
        $this->assertNull($lead->agent_id, "Agency A's agent must not keep working Agency B's lead.");
        $this->assertSame(2, LeadAssignmentHistory::where('lead_id', $lead->id)->count());
    }

    public function test_repeat_enquiry_on_an_agents_listing_reaches_that_agent(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $agency = $this->agency();

        // First enquiry while the agency has no agents → an unassigned agency lead.
        $first = $this->enquire($this->property($agency), 'Repeat Buyer', 'repeat@example.test');
        $this->assertNull($first->agent_id);

        // The same buyer later enquires about a listing assigned to an agent.
        $agent = $this->memberAgent($agency);
        $again = $this->enquire($this->property($agency, $agent), 'Repeat Buyer', 'repeat@example.test');

        $this->assertSame($first->id, $again->id, 'merged into the existing lead');
        $this->assertSame($agent->id, $again->agent_id);
        $this->assertSame(Lead::ASSIGN_PROPERTY_AGENT, $again->assignment_type);
        \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\NewLeadReceived::class, fn ($m) => $m->hasTo($agent->email));
        $this->assertSame(1, $agent->notifications()->count(), 'agent notified once, not twice');

        $this->signIn($agent)->get('/portal/crm/leads')->assertOk()->assertSee('Repeat Buyer');

        // A lead already worked by an agent keeps that agent on the next repeat enquiry.
        $other = $this->memberAgent($agency);
        $this->enquire($this->property($agency, $other), 'Repeat Buyer', 'repeat@example.test');
        $this->assertSame($agent->id, $first->fresh()->agent_id);
    }
}
