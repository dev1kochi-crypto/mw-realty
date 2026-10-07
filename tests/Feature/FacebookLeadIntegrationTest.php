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

    private function pendingPages(string $actor, array $pages): void
    {
        \Illuminate\Support\Facades\Cache::put('facebook_integration.pages.' . $actor, encrypt(array_map(
            fn ($page) => $page + ['access_token' => 'token-' . $page['id'], 'picture' => null], $pages
        )), now()->addMinutes(5));
    }

    public function test_the_super_admin_assigns_pages_to_agencies_and_agents(): void
    {
        Http::fake(['graph.facebook.com/*/subscribed_apps' => Http::response(['success' => true])]);
        $admin = $this->superAdmin();
        $agency = $this->agency();
        $agent = $this->independentAgent();
        $this->pendingPages('admin.' . $admin->id, [
            ['id' => 'PAGE1', 'name' => 'Agency Page'], ['id' => 'PAGE2', 'name' => 'Agent Page'], ['id' => 'PAGE3', 'name' => 'Skipped Page'],
        ]);

        $this->signIn($admin, 'cms')->get('/portal/crm/integrations/facebook')->assertOk()->assertSee('Assign the Pages to agencies / agents');
        $this->post('/portal/crm/integrations/facebook/pages', ['owners' => ['PAGE1' => $agency->id, 'PAGE2' => $agent->id, 'PAGE3' => null]])
            ->assertRedirect('/portal/crm/integrations/facebook');

        $this->assertSame($agency->id, FacebookPageConnection::where('page_id', 'PAGE1')->value('portal_user_id'));
        $this->assertSame($agent->id, FacebookPageConnection::where('page_id', 'PAGE2')->value('portal_user_id'));
        $this->assertFalse(FacebookPageConnection::where('page_id', 'PAGE3')->exists());
        $this->assertSame('token-PAGE1', FacebookPageConnection::where('page_id', 'PAGE1')->firstOrFail()->page_access_token);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'PAGE1/subscribed_apps') && $request['subscribed_fields'] === 'leadgen');
    }

    public function test_pages_cannot_be_assigned_to_agency_agents_or_the_admin_owner(): void
    {
        Http::fake(['graph.facebook.com/*/subscribed_apps' => Http::response(['success' => true])]);
        $admin = $this->superAdmin();
        $member = $this->memberAgent($this->agency());
        $adminOwner = \App\Services\Crm\AdminOwnerResolver::resolve();
        $this->pendingPages('admin.' . $admin->id, [['id' => 'PAGE1', 'name' => 'Page one'], ['id' => 'PAGE2', 'name' => 'Page two']]);

        $this->signIn($admin, 'cms')->post('/portal/crm/integrations/facebook/pages', ['owners' => ['PAGE1' => $member->id, 'PAGE2' => $adminOwner->id]])
            ->assertSessionHas('error');
        $this->assertSame(0, FacebookPageConnection::count());

        $results = collect($this->getJson('/portal/crm/integrations/accounts')->assertOk()->json('results'))->pluck('id');
        $this->assertNotContains($member->id, $results);
        $this->assertNotContains($adminOwner->id, $results);
    }

    public function test_an_agency_connects_its_own_pages(): void
    {
        Http::fake(['graph.facebook.com/*/subscribed_apps' => Http::response(['success' => true])]);
        $agency = $this->agency();
        $this->pendingPages('owner.' . $agency->id, [['id' => 'PAGE1', 'name' => 'MW Test Page']]);

        $this->signIn($agency)->get('/portal/crm/integrations/facebook')->assertOk()->assertSee('Choose the Pages to connect');
        $this->post('/portal/crm/integrations/facebook/pages', ['page_ids' => ['PAGE1']])->assertRedirect('/portal/crm/integrations/facebook');

        $this->assertSame($agency->id, FacebookPageConnection::where('page_id', 'PAGE1')->value('portal_user_id'));
        $this->get('/portal/crm/integrations/accounts')->assertForbidden(); // only the admin assigns to other accounts
    }

    public function test_a_page_connected_to_one_account_cannot_go_to_another(): void
    {
        Http::fake(['graph.facebook.com/*/subscribed_apps' => Http::response(['success' => true])]);
        $connection = $this->connection(); // PAGE1 → an agency
        $other = $this->agency();
        $admin = $this->superAdmin();
        $this->pendingPages('owner.' . $other->id, [['id' => 'PAGE1', 'name' => 'MW Test Page']]);
        $this->pendingPages('admin.' . $admin->id, [['id' => 'PAGE1', 'name' => 'MW Test Page']]);

        $this->signIn($other)->post('/portal/crm/integrations/facebook/pages', ['page_ids' => ['PAGE1']])->assertSessionHas('error');
        $this->signIn($admin, 'cms')->post('/portal/crm/integrations/facebook/pages', ['owners' => ['PAGE1' => $other->id]])->assertSessionHas('error');

        $this->assertSame(1, FacebookPageConnection::count());
        $this->assertSame($connection->portal_user_id, $connection->fresh()->portal_user_id);
    }

    public function test_the_callback_is_public_and_needs_the_one_time_state(): void
    {
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'user-token']),
            'graph.facebook.com/*/me/accounts*' => Http::response(['data' => [['id' => 'PAGE1', 'name' => 'MW Test Page', 'access_token' => 'page-token']]]),
        ]);
        \Illuminate\Support\Facades\Cache::put('facebook_integration.state.good-state', ['actor' => 'admin.7', 'popup' => false], now()->addMinutes(5));

        $this->get('/integrations/facebook/callback?state=bad&code=abc')->assertRedirect('/portal/crm/integrations/facebook')->assertSessionHas('error');
        $this->get('/integrations/facebook/callback?state=good-state&code=abc')->assertRedirect('/portal/crm/integrations/facebook')->assertSessionHas('toast');

        $this->assertSame('PAGE1', decrypt(\Illuminate\Support\Facades\Cache::get('facebook_integration.pages.admin.7'))[0]['id']);
        $this->get('/integrations/facebook/callback?state=good-state&code=abc')->assertSessionHas('error'); // state is one-time
    }

    public function test_a_rejected_token_flags_the_page_and_emails_the_owner_once(): void
    {
        $connection = $this->connection();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Error validating access token: The session has been invalidated.', 'code' => 190]], 400)]);
        $job = fn () => (new ImportFacebookLead('PAGE1', 'LG1'))->handle(app(\App\Services\Integrations\FacebookLeadAds::class), app(\App\Services\Integrations\FacebookLeadImporter::class));

        $job();
        $job();

        $connection->refresh();
        $this->assertNotNull($connection->needs_reconnect_at);
        $this->assertStringContainsString('session has been invalidated', $connection->last_error);
        Mail::assertQueued(\App\Mail\FacebookPageReconnectMail::class, 1);
        Mail::assertQueued(\App\Mail\FacebookPageReconnectMail::class, fn ($mail) => $mail->hasTo($connection->owner->email));
    }

    public function test_the_same_person_from_the_same_ad_is_skipped_and_from_a_new_ad_goes_to_history(): void
    {
        Http::fake(); // ad-name lookups
        $connection = $this->connection();
        $importer = app(\App\Services\Integrations\FacebookLeadImporter::class);

        $first = $importer->import($connection, $this->graphLead(['id' => 'LG1']));
        $this->assertNull($importer->import($connection, $this->graphLead(['id' => 'LG2'])), 'same email + same ad → already in the CRM');

        $merged = $importer->import($connection, $this->graphLead(['id' => 'LG3', 'ad_name' => 'Marina Flats — November']));
        $this->assertSame($first->id, $merged->id, 'no duplicate lead');
        $this->assertSame(1, Lead::count());
        $this->assertSame(1, $first->notesHistory()->where('type', \App\Models\LeadNote::TYPE_ENQUIRY)->count(), 'the new ad is logged in the lead history');

        $this->assertNull($importer->import($connection, $this->graphLead(['id' => 'LG4', 'ad_name' => 'Marina Flats — November'])), 'latest source is now that ad');
    }

    public function test_connecting_with_import_starts_a_background_import_of_existing_leads(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        Http::fake(['graph.facebook.com/*/subscribed_apps' => Http::response(['success' => true])]);
        $agency = $this->agency();
        $this->pendingPages('owner.' . $agency->id, [['id' => 'PAGE1', 'name' => 'MW Test Page']]);

        $this->signIn($agency)->post('/portal/crm/integrations/facebook/pages', ['page_ids' => ['PAGE1'], 'import_existing' => 1, 'import_days' => 30]);

        $connection = FacebookPageConnection::firstOrFail();
        $this->assertSame('queued', $connection->import_status);
        \Illuminate\Support\Facades\Bus::assertDispatchedAfterResponse(\App\Jobs\ImportFacebookPageLeads::class, fn ($job) => $job->connectionId === $connection->id && $job->context === 'import'
            && abs($job->since - now()->subDays(30)->getTimestamp()) < 60);
    }

    public function test_accounts_only_reach_their_own_pages(): void
    {
        $connection = $this->connection();

        $this->signIn($this->agency())->delete("/portal/crm/integrations/facebook/{$connection->id}")->assertNotFound();
        $this->assertTrue($connection->exists());
    }

    public function test_an_agency_agent_connects_their_own_pages_as_personal_leads(): void
    {
        Http::fake(['graph.facebook.com/*/subscribed_apps' => Http::response(['success' => true])]);
        $agency = $this->agency();
        $agent = $this->memberAgent($agency);
        $this->pendingPages('owner.' . $agent->id, [['id' => 'PAGE1', 'name' => 'Agent Own Page']]);

        $this->signIn($agent)->get('/portal/crm/integrations/facebook')->assertOk()->assertSee('Choose the Pages to connect')->assertSee('personal leads');
        $this->post('/portal/crm/integrations/facebook/pages', ['page_ids' => ['PAGE1']])->assertRedirect('/portal/crm/integrations/facebook');

        $connection = FacebookPageConnection::where('page_id', 'PAGE1')->firstOrFail();
        $this->assertSame($agent->id, $connection->portal_user_id);

        app(\App\Services\Integrations\FacebookLeadImporter::class)->import($connection, $this->graphLead(['ad_id' => null]));
        $lead = Lead::firstOrFail();
        $this->assertSame($agent->id, $lead->portal_user_id, 'the agent owns the lead, not the agency');
        $this->assertSame($agent->id, $lead->agent_id);

        // The agency doesn't see or manage its agent's Page.
        $this->signIn($agency)->get('/portal/crm/integrations/facebook')->assertDontSee('Agent Own Page');
        $this->delete("/portal/crm/integrations/facebook/{$connection->id}")->assertNotFound();
    }

    public function test_manual_assignment_keeps_agency_facebook_leads_unassigned(): void
    {
        $agency = $this->agency();
        $this->memberAgent($agency);
        $this->signIn($agency)->put('/portal/agents/lead-assignment', ['mode' => 'manual'])->assertSessionHas('toast');

        app(\App\Services\Integrations\FacebookLeadImporter::class)->import($this->connection($agency), $this->graphLead(['ad_id' => null]));

        $lead = Lead::firstOrFail();
        $this->assertNull($lead->agent_id);
        $this->assertSame(Lead::ASSIGN_AGENCY_UNASSIGNED, $lead->assignment_type);
    }

    public function test_round_robin_only_covers_the_chosen_kinds_of_lead(): void
    {
        $agency = $this->agency();
        $agent = $this->memberAgent($agency);
        $importer = app(\App\Services\Integrations\FacebookLeadImporter::class);
        $connection = $this->connection($agency);

        // Generic only: a Facebook lead waits unassigned.
        $this->signIn($agency)->put('/portal/agents/lead-assignment', ['mode' => 'automatic', 'sources' => ['generic']]);
        $importer->import($connection, $this->graphLead(['ad_id' => null]));
        $this->assertNull(Lead::firstOrFail()->agent_id);

        // Facebook ticked: the next one is round-robined to the agent.
        $this->put('/portal/agents/lead-assignment', ['mode' => 'automatic', 'sources' => ['generic', 'facebook']]);
        $importer->import($connection, $this->graphLead(['id' => 'LG9', 'ad_id' => null, 'field_data' => [['name' => 'email', 'values' => ['new@example.test']]]]));
        $this->assertSame($agent->id, Lead::where('email', 'new@example.test')->value('agent_id'));

        // Automatic with nothing ticked is refused.
        $this->put('/portal/agents/lead-assignment', ['mode' => 'automatic', 'sources' => []])->assertSessionHas('error');
    }

    public function test_sync_pulls_new_leads_from_every_form_and_sends_one_summary_email(): void
    {
        $agency = $this->agency();
        $connection = $this->connection($agency);
        $connection->forceFill(['last_synced_at' => now()->subDay()])->save();
        Http::fake([
            'graph.facebook.com/*/PAGE1/leadgen_forms*' => Http::response(['data' => [['id' => 'F1', 'name' => 'Villa form'], ['id' => 'F2', 'name' => 'Flat form', 'status' => 'ARCHIVED']]]),
            'graph.facebook.com/*/F1/leads*' => Http::response(['data' => [$this->graphLead(['id' => 'LG9'])]]),
            'graph.facebook.com/*/F2/leads*' => Http::response(['data' => [$this->graphLead(['id' => 'LG10', 'adset_name' => 'Flats set', 'field_data' => [['name' => 'email', 'values' => ['other@example.test']]]])]]),
        ]);

        $this->signIn($agency)->post("/portal/crm/integrations/facebook/{$connection->id}/sync")->assertRedirect()->assertSessionHas('toast');

        $this->assertSame(2, Lead::where('portal_user_id', $agency->id)->count());
        $this->assertSame('Flats set', Lead::with('source')->where('email', 'other@example.test')->firstOrFail()->source->name, 'the ad set name is the Source');
        $this->assertNotNull($connection->fresh()->last_synced_at);
        Mail::assertQueued(\App\Mail\FacebookLeadsSyncedMail::class, 1);
        Mail::assertNotQueued(\App\Mail\NewLeadReceived::class); // bulk: no email per lead
    }

    public function test_sync_all_leads_runs_in_the_background_without_a_date_limit(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        $agency = $this->agency();
        $connection = $this->connection($agency);

        $this->signIn($agency)->post("/portal/crm/integrations/facebook/{$connection->id}/sync?all=1")->assertRedirect();

        \Illuminate\Support\Facades\Bus::assertDispatchedAfterResponse(\App\Jobs\ImportFacebookPageLeads::class, fn ($job) => $job->since === null && $job->context === 'sync');
    }
}
