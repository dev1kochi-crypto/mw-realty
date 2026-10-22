<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsAgencies;
use Tests\TestCase;

/** CRM Reports (/api/crm/reports): Leads / Properties for everyone on a reports plan, Agents for agencies only. */
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

        $this->crmApi($agency);
        $this->getJson('/api/crm/reports')->assertOk()->assertJsonPath('report', 'leads')->assertJsonPath('locked', null)
            ->assertJsonFragment(['key' => 'agents', 'label' => 'Agents']);
        foreach ([7, 30, 90, 365] as $range) {
            $this->getJson("/api/crm/reports/leads?range={$range}")->assertOk()->assertJsonPath('days', $range);
        }
        $this->getJson('/api/crm/reports/properties?range=365')->assertOk()->assertSee('Marina Loft');
        $this->getJson('/api/crm/reports/agents')->assertOk()
            ->assertSee('Alice Member')
            ->assertDontSee('Bob Elsewhere');
    }

    public function test_agent_gets_leads_and_properties_but_not_agents_report(): void
    {
        $agent = $this->independentAgent(['plan_id' => $this->plan(['reports_access' => true, 'agent_limit' => 0])->id]);

        $this->crmApi($agent);
        $this->getJson('/api/crm/reports/leads')->assertOk()->assertJsonMissing(['key' => 'agents', 'label' => 'Agents']);
        $this->getJson('/api/crm/reports/properties')->assertOk();
        $this->getJson('/api/crm/reports/agents')->assertNotFound();
    }

    public function test_reports_are_locked_without_the_plan_feature(): void
    {
        $agency = $this->agency([], $this->plan(['reports_access' => false]));

        $this->crmApi($agency);
        foreach (['leads', 'properties', 'agents'] as $report) {
            $this->getJson("/api/crm/reports/{$report}")->assertOk()->assertJsonMissingPath('data')->assertJsonStructure(['locked' => ['plan', 'agency']]);
        }
        $this->getJson('/api/crm/reports/sales/export')->assertForbidden();
    }

    public function test_sales_counts_won_deals_at_property_price_and_excludes_lost(): void
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

        $data = app(\App\Services\Crm\PortalReportService::class)->sales($agency, 30);
        $this->assertEquals(1500000, $data['kpis']['won_value']);
        $this->assertSame(1, $data['kpis']['won_count']);
        $this->assertEquals(900000, $data['kpis']['lost_value'], '"Dropped" counts as lost, not won');
        $this->assertEquals(50.0, $data['kpis']['win_rate']);
        $this->assertEquals(120000, $data['kpis']['pipeline_value']);
        $this->assertSame(1, $data['deals']->total(), 'the deal list shows won deals by default');
        $this->assertNull($data['plans'], 'plan sales are Super Admin only');

        $this->crmApi($agency);
        $this->getJson('/api/crm/reports/sales?range=90')->assertOk()
            ->assertJsonPath('data.kpis.won_value', 1500000)->assertSee('Winning Buyer')->assertSee('Alice Member')
            ->assertJsonPath('data.plans', null);
        $this->getJson('/api/crm/reports/sales?range=90&outcome=lost')->assertOk()->assertSee('Gone Buyer');

        $names = fn (array $filters) => app(\App\Services\Crm\PortalReportService::class)->salesDeals($agency, 90, $filters)->pluck('leads.name')->all();
        $this->assertSame(['Winning Buyer'], $names([]));
        $this->assertSame(['Gone Buyer'], $names(['outcome' => 'lost']));
        $this->assertSame(['Gone Buyer'], $names(['outcome' => 'all', 'q' => 'Gone']));
        $this->assertSame([], $names(['listing' => 'rent']));

        $csv = $this->get('/api/crm/reports/sales/export?range=90');
        $csv->assertOk();
        $this->assertStringContainsString('Winning Buyer', $csv->streamedContent());
        $this->assertStringContainsString('1500000', $csv->streamedContent());

        // Moving back to an open stage clears the close date.
        $wonLead->update(['stage_id' => $open->id]);
        $this->assertNull($wonLead->fresh()->closed_at);
    }

    public function test_old_report_links_redirect_to_the_crm_app(): void
    {
        $agency = $this->agency([], $this->plan(['reports_access' => true]));

        $this->signIn($agency);
        $this->get('/portal/crm/reports/revenue?range=90')->assertRedirect('/crm/reports/sales?range=90');
        $this->get('/portal/crm/reports')->assertRedirect('/crm/reports/leads');
    }

    public function test_super_admin_sales_includes_plan_sales(): void
    {
        $agency = $this->agency();
        \App\Models\PlanPayment::create([
            'portal_user_id' => $agency->id, 'plan_id' => $agency->plan_id, 'plan_name' => 'Agency Pro',
            'amount' => 499, 'status' => 'paid', 'billing_cycle' => 'monthly', 'paid_at' => now(),
            'period_year' => now()->year, 'period_month' => now()->month,
        ]);

        $this->crmApiAsSuperAdmin()->getJson('/api/crm/reports/sales')->assertOk()
            ->assertJsonPath('data.plans.total', 499)->assertJsonPath('data.plans.by_plan.0.name', 'Agency Pro');
    }

    public function test_super_admin_sees_every_agencys_agents(): void
    {
        $this->memberAgent($this->agency(), attributes: ['name' => 'Alice Member']);
        $this->memberAgent($this->agency(), attributes: ['name' => 'Bob Elsewhere']);

        $this->crmApiAsSuperAdmin();
        $this->getJson('/api/crm/reports/leads')->assertOk()->assertJsonPath('is_admin', true);
        $this->getJson('/api/crm/reports/agents')->assertOk()->assertSee('Alice Member')->assertSee('Bob Elsewhere');
    }
}
