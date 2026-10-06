<?php

namespace Tests\Feature;

use App\Jobs\ImportFacebookLead;
use App\Models\FacebookPageConnection;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Concerns\BuildsAgencies;
use Tests\TestCase;

/** CRM › Integrations: Facebook Lead Ads → CRM leads (webhook, import, connect, sync). */
class FacebookLeadIntegrationTest extends TestCase
{
    use RefreshDatabase, BuildsAgencies;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.facebook_leads' => [
            'app_id' => '123', 'app_secret' => 'app-secret', 'verify_token' => 'verify-me', 'graph_version' => 'v21.0',
        ]]);
        Notification::fake();
        Mail::fake();
    }

    private function graphLead(array $overrides = []): array
    {
        return array_merge([
            'id' => 'LG1',
            'created_time' => '2026-10-05T10:00:00+0000',
            'form_id' => 'F1',
            'ad_id' => 'AD1',
            'ad_name' => 'Palm Villas — October',
            'campaign_name' => 'Q4 Villas',
            'field_data' => [
                ['name' => 'full_name', 'values' => ['Sara Khan']],
                ['name' => 'email', 'values' => ['sara@example.test']],
                ['name' => 'phone_number', 'values' => ['+971501234567']],
                ['name' => 'what_is_your_budget?', 'values' => ['AED 3M']],
            ],
        ], $overrides);
    }

    private function connection(?\App\Models\PortalUser $owner = null): FacebookPageConnection
    {
        return FacebookPageConnection::create([
            'portal_user_id' => ($owner ?? $this->agency())->id,
            'page_id' => 'PAGE1', 'page_name' => 'MW Test Page', 'page_access_token' => 'page-token', 'subscribed_at' => now(),
        ]);
    }

    public function test_webhook_verification_handshake(): void
    {
        $this->get('/api/webhooks/facebook?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=abc123')
            ->assertOk()->assertSee('abc123');
        $this->get('/api/webhooks/facebook?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=abc123')->assertForbidden();
    }

    public function test_webhook_needs_a_valid_signature_and_queues_each_lead(): void
    {
        Queue::fake();
        $body = json_encode(['object' => 'page', 'entry' => [['id' => 'PAGE1', 'changes' => [
            ['field' => 'leadgen', 'value' => ['leadgen_id' => 'LG1', 'page_id' => 'PAGE1', 'form_id' => 'F1']],
        ]]]]);

        $this->call('POST', '/api/webhooks/facebook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=bad'], $body)->assertForbidden();
        Queue::assertNothingPushed();

        $signature = 'sha256=' . hash_hmac('sha256', $body, 'app-secret');
        $this->call('POST', '/api/webhooks/facebook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $signature], $body)->assertOk();
        Queue::assertPushed(ImportFacebookLead::class, fn ($job) => $job->pageId === 'PAGE1' && $job->leadgenId === 'LG1');
    }

    public function test_a_facebook_lead_becomes_a_crm_lead_with_the_ad_name_as_source_once(): void
    {
        $connection = $this->connection();
        Http::fake(['graph.facebook.com/*' => Http::response($this->graphLead())]);

        (new ImportFacebookLead('PAGE1', 'LG1'))->handle(app(\App\Services\Integrations\FacebookLeadAds::class), app(\App\Services\Integrations\FacebookLeadImporter::class));
        (new ImportFacebookLead('PAGE1', 'LG1'))->handle(app(\App\Services\Integrations\FacebookLeadAds::class), app(\App\Services\Integrations\FacebookLeadImporter::class));

        $this->assertSame(1, Lead::count(), 'a repeated webhook is not imported twice');
        $lead = Lead::with('source')->firstOrFail();
        $this->assertSame($connection->portal_user_id, $lead->portal_user_id);
        $this->assertSame('Sara Khan', $lead->name);
        $this->assertSame('sara@example.test', $lead->email);
        $this->assertSame('+971', $lead->phone_country_code);
        $this->assertSame('501234567', $lead->phone);
        $this->assertSame('Palm Villas — October', $lead->source->name);
        $this->assertStringContainsString('What is your budget?: AED 3M', $lead->message);
        $this->assertSame(1, $connection->fresh()->leads_count);
    }

    public function test_a_lead_without_an_ad_gets_the_facebook_source(): void
    {
        $connection = $this->connection();
        app(\App\Services\Integrations\FacebookLeadImporter::class)->import($connection, $this->graphLead(['id' => 'LG2', 'ad_id' => null, 'ad_name' => null]));

        $this->assertSame('Facebook Lead Ads', Lead::with('source')->firstOrFail()->source->name);
    }

    public function test_the_super_admin_links_pages_to_agencies_and_agents(): void
    {
        Http::fake(['graph.facebook.com/*/subscribed_apps' => Http::response(['success' => true])]);
        $admin = $this->superAdmin();
        $agency = $this->agency();
        $agent = $this->independentAgent();
        \Illuminate\Support\Facades\Cache::put('facebook_integration.pages.' . $admin->id, encrypt([
            ['id' => 'PAGE1', 'name' => 'Agency Page', 'access_token' => 'token-1', 'picture' => null],
            ['id' => 'PAGE2', 'name' => 'Agent Page', 'access_token' => 'token-2', 'picture' => null],
            ['id' => 'PAGE3', 'name' => 'Skipped Page', 'access_token' => 'token-3', 'picture' => null],
        ]), now()->addMinutes(5));

        $this->signIn($admin, 'cms')->get('/portal/crm/integrations')->assertOk()->assertSee('Link the Pages to agencies / agents');
        $this->post('/portal/crm/integrations/facebook/pages', ['owners' => ['PAGE1' => $agency->id, 'PAGE2' => $agent->id, 'PAGE3' => null]])
            ->assertRedirect('/portal/crm/integrations');

        $this->assertSame($agency->id, FacebookPageConnection::where('page_id', 'PAGE1')->value('portal_user_id'));
        $this->assertSame($agent->id, FacebookPageConnection::where('page_id', 'PAGE2')->value('portal_user_id'));
        $this->assertFalse(FacebookPageConnection::where('page_id', 'PAGE3')->exists());
        $this->assertSame('token-1', FacebookPageConnection::where('page_id', 'PAGE1')->firstOrFail()->page_access_token);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'PAGE1/subscribed_apps') && $request['subscribed_fields'] === 'leadgen');
    }

    public function test_pages_cannot_be_linked_to_agency_agents_or_the_admin_owner(): void
    {
        Http::fake(['graph.facebook.com/*/subscribed_apps' => Http::response(['success' => true])]);
        $admin = $this->superAdmin();
        $member = $this->memberAgent($this->agency());
        $adminOwner = \App\Services\Crm\AdminOwnerResolver::resolve();
        \Illuminate\Support\Facades\Cache::put('facebook_integration.pages.' . $admin->id, encrypt([
            ['id' => 'PAGE1', 'name' => 'Page one', 'access_token' => 't1', 'picture' => null],
            ['id' => 'PAGE2', 'name' => 'Page two', 'access_token' => 't2', 'picture' => null],
        ]), now()->addMinutes(5));

        $this->signIn($admin, 'cms')->post('/portal/crm/integrations/facebook/pages', ['owners' => ['PAGE1' => $member->id, 'PAGE2' => $adminOwner->id]])
            ->assertSessionHas('error');
        $this->assertSame(0, FacebookPageConnection::count());

        $results = collect($this->getJson('/portal/crm/integrations/accounts')->assertOk()->json('results'))->pluck('id');
        $this->assertNotContains($member->id, $results);
        $this->assertNotContains($adminOwner->id, $results);
    }

    public function test_the_super_admin_moves_a_page_to_another_account(): void
    {
        $connection = $this->connection();
        $agent = $this->independentAgent();

        $this->signIn($this->superAdmin(), 'cms')->patch("/portal/crm/integrations/facebook/{$connection->id}/owner", ['owner_id' => $agent->id])->assertRedirect();
        $this->assertSame($agent->id, $connection->fresh()->portal_user_id);
    }

    public function test_the_callback_is_public_and_needs_the_one_time_state(): void
    {
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'user-token']),
            'graph.facebook.com/*/me/accounts*' => Http::response(['data' => [['id' => 'PAGE1', 'name' => 'MW Test Page', 'access_token' => 'page-token']]]),
        ]);
        \Illuminate\Support\Facades\Cache::put('facebook_integration.state.good-state', 7, now()->addMinutes(5));

        $this->get('/integrations/facebook/callback?state=bad&code=abc')->assertRedirect('/portal/crm/integrations')->assertSessionHas('error');
        $this->get('/integrations/facebook/callback?state=good-state&code=abc')->assertRedirect('/portal/crm/integrations')->assertSessionHas('toast');

        $this->assertSame('PAGE1', decrypt(\Illuminate\Support\Facades\Cache::get('facebook_integration.pages.7'))[0]['id']);
        $this->get('/integrations/facebook/callback?state=good-state&code=abc')->assertSessionHas('error'); // state is one-time
    }

    public function test_agencies_see_their_pages_but_cannot_connect_or_disconnect(): void
    {
        $agency = $this->agency();
        $connection = $this->connection($agency);

        $this->signIn($agency)->get('/portal/crm/integrations')->assertOk()->assertSee('MW Test Page')->assertSee('MW Realty connects Facebook Pages for you');
        $this->get('/portal/crm/integrations/facebook/connect')->assertForbidden();
        $this->get('/portal/crm/integrations/accounts')->assertForbidden();
        $this->delete("/portal/crm/integrations/facebook/{$connection->id}")->assertForbidden();
        $this->patch("/portal/crm/integrations/facebook/{$connection->id}/owner", ['owner_id' => $agency->id])->assertForbidden();
    }

    public function test_agency_agents_see_their_agency_manages_pages(): void
    {
        $agent = $this->memberAgent($this->agency());

        $this->signIn($agent)->get('/portal/crm/integrations')->assertOk()->assertSee('Facebook Pages are linked to your agency');
        $this->get('/portal/crm/integrations/facebook/connect')->assertForbidden();
    }

    public function test_sync_pulls_recent_leads_from_the_pages_forms(): void
    {
        $agency = $this->agency();
        $connection = $this->connection($agency);
        Http::fake([
            'graph.facebook.com/*/PAGE1/leadgen_forms*' => Http::response(['data' => [['id' => 'F1', 'name' => 'Villa form']]]),
            'graph.facebook.com/*/F1/leads*' => Http::response(['data' => [$this->graphLead(['id' => 'LG9'])]]),
        ]);

        $this->signIn($agency)->post("/portal/crm/integrations/facebook/{$connection->id}/sync")->assertRedirect()->assertSessionHas('toast', '1 new lead added from MW Test Page.');
        $this->assertSame(1, Lead::where('portal_user_id', $agency->id)->count());
        $this->assertNotNull($connection->fresh()->last_synced_at);
    }
}
