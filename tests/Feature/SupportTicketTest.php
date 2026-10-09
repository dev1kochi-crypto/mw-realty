<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Notifications\SupportTicketNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsAgencies;
use Tests\TestCase;

/** CRM Contact Us tickets (/api/crm/support): raise → admin reply → client reply → solved, plus isolation. */
class SupportTicketTest extends TestCase
{
    use RefreshDatabase, BuildsAgencies;

    public function test_full_ticket_lifecycle(): void
    {
        Storage::fake('kyc');
        Notification::fake();
        $agent = $this->independentAgent();
        $admin = $this->superAdmin();
        // superAdmin() syncs the role to a fixed permission list, dropping what the migration granted.
        $admin->roles->first()->givePermissionTo(['support-tickets.view', 'support-tickets.edit']);

        $this->crmApi($agent);
        $this->getJson('/api/crm/support/tickets')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/crm/support/meta')->assertOk()->assertSee('KYC / Verification');

        $this->post('/api/crm/support/tickets', [
            'category' => 'payments', 'priority' => 'high', 'subject' => 'Invoice missing',
            'message' => 'My September invoice is not showing.',
            'attachment_document' => UploadedFile::fake()->image('screen.png'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $ticket = SupportTicket::firstOrFail();
        $this->assertSame(SupportTicket::OPEN, $ticket->status);
        $this->assertSame('client', $ticket->last_reply_by);
        Notification::assertSentTo($admin, SupportTicketNotification::class);

        $message = $ticket->messages()->first();
        Storage::disk('kyc')->assertExists($message->attachment_path);
        $this->getJson("/api/crm/support/tickets/{$ticket->id}")->assertOk()->assertSee('Invoice missing')->assertSee('screen.png');
        $this->get("/api/crm/support/tickets/{$ticket->id}/attachments/{$message->id}")->assertOk();
        $this->getJson('/api/crm/support/tickets?status=active')->assertOk()->assertSee($ticket->reference());

        // Admin replies → awaiting client, client notified.
        $this->signIn($admin, 'cms');
        $this->get('/admin/support-tickets')->assertOk()->assertSee($ticket->reference());
        $this->get('/admin/support-tickets?status=needs_reply&search=' . $ticket->reference())->assertOk()->assertSee('Invoice missing');
        $this->get("/admin/support-tickets/{$ticket->id}")->assertOk()->assertSee('My September invoice');
        $this->post("/admin/support-tickets/{$ticket->id}/reply", ['message' => 'Re-issued it now.'])->assertRedirect();
        $this->assertSame(SupportTicket::AWAITING_CLIENT, $ticket->fresh()->status);
        Notification::assertSentTo($agent, SupportTicketNotification::class);

        // Client replies → back in admin queue; then marks solved.
        $this->crmApi($agent);
        $this->getJson('/api/crm/support/tickets')->assertOk()->assertJsonPath('stats.awaiting', 1);
        $this->postJson("/api/crm/support/tickets/{$ticket->id}/reply", ['message' => 'Got it, thanks'])->assertOk();
        $this->assertSame(SupportTicket::OPEN, $ticket->fresh()->status);
        $this->postJson("/api/crm/support/tickets/{$ticket->id}/resolve")->assertOk();
        $this->assertSame(SupportTicket::RESOLVED, $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->resolved_at);
        $this->postJson("/api/crm/support/tickets/{$ticket->id}/reopen")->assertOk();
        $this->assertSame(SupportTicket::OPEN, $ticket->fresh()->status);

        // Admin closes → client can no longer reply.
        $this->signIn($admin, 'cms');
        $this->put("/admin/support-tickets/{$ticket->id}", ['status' => 'closed', 'priority' => 'urgent'])->assertRedirect();
        $this->assertSame('urgent', $ticket->fresh()->priority);

        $this->crmApi($agent);
        $this->postJson("/api/crm/support/tickets/{$ticket->id}/reply", ['message' => 'one more'])->assertUnprocessable();
        $this->assertSame(SupportTicket::CLOSED, $ticket->fresh()->status);
        $this->getJson("/api/crm/support/tickets/{$ticket->id}")->assertOk()->assertJsonPath('is_closed', true);

        // Every change is in the thread: 2 client + 1 admin messages (the post-close reply was refused), status/priority notes.
        $this->assertSame(3, $ticket->messages()->where('author_type', '!=', 'system')->count());
        $this->assertGreaterThanOrEqual(5, $ticket->messages()->where('author_type', 'system')->count());
    }

    public function test_issue_type_is_required_and_validated(): void
    {
        $this->crmApi($this->independentAgent());

        $this->postJson('/api/crm/support/tickets', ['priority' => 'normal', 'subject' => 'x', 'message' => 'y'])
            ->assertJsonValidationErrors('category');
        $this->postJson('/api/crm/support/tickets', ['category' => 'nonsense', 'priority' => 'normal', 'subject' => 'x', 'message' => 'y'])
            ->assertJsonValidationErrors('category');
        $this->assertSame(0, SupportTicket::count());
    }

    public function test_clients_cannot_see_each_others_tickets(): void
    {
        Notification::fake();
        $owner = $this->independentAgent();
        $other = $this->agency();

        $this->crmApi($owner)->postJson('/api/crm/support/tickets', [
            'category' => 'other', 'priority' => 'normal', 'subject' => 'Private question', 'message' => 'secret',
        ])->assertCreated();
        $ticket = SupportTicket::firstOrFail();

        $this->crmApi($other);
        $this->getJson('/api/crm/support/tickets')->assertOk()->assertDontSee('Private question');
        $this->getJson("/api/crm/support/tickets/{$ticket->id}")->assertNotFound();
        $this->postJson("/api/crm/support/tickets/{$ticket->id}/reply", ['message' => 'hi'])->assertNotFound();
        $this->postJson("/api/crm/support/tickets/{$ticket->id}/resolve")->assertNotFound();
    }
}
