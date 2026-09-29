<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsAgencies;
use Tests\TestCase;

/** Portal dashboard: renders, one status banner for pending accounts, greeting by time of day. */
class PortalDashboardTest extends TestCase
{
    use RefreshDatabase, BuildsAgencies;

    public function test_pending_account_sees_a_single_status_banner(): void
    {
        $html = $this->signIn($this->independentAgent(['status' => 'pending', 'kyc_review_status' => 'draft']))
            ->get('/portal/dashboard')->assertOk()->getContent();

        // The banner itself, not its __icon / __body / … parts.
        $this->assertSame(1, preg_match_all('/class="portal-status-banner[\s"]/', $html), 'exactly one status banner');
        $this->assertStringNotContainsString('Your CRM access is locked for now', $html);
        $this->assertStringContainsString('id="dlGreeting"', $html);
    }

    public function test_approved_agency_dashboard_renders_with_top_listings(): void
    {
        $agency = $this->agency();
        $this->enquire($this->property($agency, null, ['translations' => ['en' => ['title' => 'Busy Listing']]]), 'Some Buyer');

        $this->signIn($agency)->get('/portal/dashboard')->assertOk()->assertSee('Busy Listing')->assertDontSee('portal-status-banner', false);
    }
}
