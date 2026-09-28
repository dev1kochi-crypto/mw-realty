<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsAgencies;
use Tests\TestCase;

/** Portal Reports: Leads / Properties for everyone on a reports plan, Agents for agencies only. */
class PortalReportsTest extends TestCase
{
    use RefreshDatabase, BuildsAgencies;

    public function test_agency_sees_all_three_reports_with_its_own_agents_only(): void
    {
        $agency = $this->agency([], $this->plan(['reports_access' => true]));
        $agent = $this->memberAgent($agency, attributes: ['name' => 'Alice Member']);
        $this->enquire($this->property($agency, $agent, ['translations' => ['en' => ['title' => 'Marina Loft']]]));

        $otherAgency = $this->agency([], $this->plan(['reports_access' => true]));
        $this->memberAgent($otherAgency, attributes: ['name' => 'Bob Elsewhere']);

        $this->signIn($agency);
        $this->get('/portal/crm/reports')->assertOk()->assertSee('Leads by Source')->assertSee('Agents');
        foreach ([7, 30, 90, 365] as $range) {
            $this->get("/portal/crm/reports/leads?range={$range}")->assertOk();
        }
        $this->get('/portal/crm/reports/properties?range=365')->assertOk()->assertSee('Marina Loft');
        $this->get('/portal/crm/reports/agents')->assertOk()
            ->assertSee('Alice Member')
            ->assertDontSee('Bob Elsewhere');
    }

    public function test_agent_gets_leads_and_properties_but_not_agents_report(): void
    {
        $agent = $this->independentAgent(['plan_id' => $this->plan(['reports_access' => true, 'agent_limit' => 0])->id]);

        $this->signIn($agent);
        $this->get('/portal/crm/reports/leads')->assertOk()->assertDontSee('fa-user-tie"></i>Agents', false);
        $this->get('/portal/crm/reports/properties')->assertOk();
        $this->get('/portal/crm/reports/agents')->assertNotFound();
    }

    public function test_reports_are_locked_without_the_plan_feature(): void
    {
        $agency = $this->agency([], $this->plan(['reports_access' => false]));

        $this->signIn($agency);
        foreach (['leads', 'properties', 'agents'] as $report) {
            $this->get("/portal/crm/reports/{$report}")->assertOk()->assertSee('Unlock reports');
        }
    }

    public function test_revenue_counts_won_deals_at_property_price_and_excludes_lost(): void
    {
        $agency = $this->agency([], $this->plan(['reports_access' => true]));
        $agent = $this->memberAgent($agency, attributes: ['name' => 'Alice Member']);
        $won = \App\Models\LeadStage::create(['portal_user_id' => $agency->id, 'name' => 'Closed Won', 'color' => '#14b8a6', 'is_closed' => true, 'order_index' => 1]);
        $dropped = \App\Models\LeadStage::create(['portal_user_id' => $agency->id, 'name' => 'Dropped', 'color' => '#ef4444', 'is_closed' => true, 'order_index' => 2]);
        $open = \App\Models\LeadStage::create(['portal_user_id' => $agency->id, 'name' => 'Negotiation', 'color' => '#8b5cf6', 'order_index' => 0]);

        $wonLead = $this->enquire($this->property($agency, $agent, ['price' => 1500000, 'listing_type' => 'sale']), 'Winning Buyer');
        $lostLead = $this->enquire($this->property($agency, $agent, ['price' => 900000, 'listing_type' => 'sale']), 'Gone Buyer');
        $openLead = $this->enquire($this->property($agency, null, ['price' => 120000, 'listing_type' => 'rent']), 'Maybe Buyer');

        $wonLead->update(['stage_id' => $won->id]);
        $lostLead->update(['stage_id' => $dropped->id]);
        $openLead->update(['stage_id' => $open->id]);
        $this->assertNotNull($wonLead->fresh()->closed_at, 'closed_at is stamped when entering a closed stage');
        $this->assertNull($openLead->fresh()->closed_at);

        $data = app(\App\Services\Crm\PortalReportService::class)->revenue($agency, 30);
        $this->assertEquals(1500000, $data['kpis']['won_value']);
        $this->assertSame(1, $data['kpis']['won_count']);
        $this->assertEquals(900000, $data['kpis']['lost_value'], '"Dropped" counts as lost, not won');
        $this->assertEquals(50.0, $data['kpis']['win_rate']);
        $this->assertEquals(120000, $data['kpis']['pipeline_value']);
        $this->assertNull($data['plans'], 'plan revenue is Super Admin only');

        $this->signIn($agency);
        $this->get('/portal/crm/reports/revenue?range=90')->assertOk()
            ->assertSee('AED 1,500,000')->assertSee('Winning Buyer')->assertSee('Alice Member')
            ->assertDontSee('MW Realty Plan Revenue');

        // Moving back to an open stage clears the close date.
        $wonLead->update(['stage_id' => $open->id]);
        $this->assertNull($wonLead->fresh()->closed_at);
    }

    public function test_super_admin_revenue_includes_plan_revenue(): void
    {
        $agency = $this->agency();
        \App\Models\PlanPayment::create([
            'portal_user_id' => $agency->id, 'plan_id' => $agency->plan_id, 'plan_name' => 'Agency Pro',
            'amount' => 499, 'status' => 'paid', 'billing_cycle' => 'monthly', 'paid_at' => now(),
            'period_year' => now()->year, 'period_month' => now()->month,
        ]);

        $this->signIn($this->superAdmin(), 'cms');
        $this->get('/portal/crm/reports/revenue')->assertOk()->assertSee('MW Realty Plan Revenue')->assertSee('AED 499');
    }

    public function test_super_admin_sees_every_agencys_agents(): void
    {
        $this->memberAgent($this->agency(), attributes: ['name' => 'Alice Member']);
        $this->memberAgent($this->agency(), attributes: ['name' => 'Bob Elsewhere']);

        $this->signIn($this->superAdmin(), 'cms');
        $this->get('/portal/crm/reports/leads')->assertOk()->assertSee("every account's data", false);
        $this->get('/portal/crm/reports/agents')->assertOk()->assertSee('Alice Member')->assertSee('Bob Elsewhere');
    }
}
