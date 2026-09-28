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

/** Portal Contact Us tickets: raise → admin reply → client reply → solved, plus isolation. */
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

        $this->signIn($agent);
        $this->get('/portal/contact')->assertOk()->assertSee('No tickets yet');
        $this->get('/portal/contact/tickets/create')->assertOk()->assertSee('KYC / Verification');

        $this->post('/portal/contact/tickets', [
            'category' => 'payments', 'priority' => 'high', 'subject' => 'Invoice missing',
            'message' => 'My September invoice is not showing.',
            'attachment_document' => UploadedFile::fake()->image('screen.png'),
        ])->assertRedirect();

        $ticket = SupportTicket::firstOrFail();
        $this->assertSame(SupportTicket::OPEN, $ticket->status);
        $this->assertSame('client', $ticket->last_reply_by);
        Notification::assertSentTo($admin, SupportTicketNotification::class);

        $message = $ticket->messages()->first();
        Storage::disk('kyc')->assertExists($message->attachment_path);
        $this->get("/portal/contact/tickets/{$ticket->id}")->assertOk()->assertSee('Invoice missing')->assertSee('screen.png');
        $this->get("/portal/contact/tickets/{$ticket->id}/attachments/{$message->id}")->assertOk();
        $this->get('/portal/contact?status=active')->assertOk()->assertSee($ticket->reference());

        // Admin replies → awaiting client, client notified.
        $this->app['auth']->forgetGuards();
        $this->signIn($admin, 'cms');
        $this->get('/admin/support-tickets')->assertOk()->assertSee($ticket->reference());
        $this->get('/admin/support-tickets?status=needs_reply&search=' . $ticket->reference())->assertOk()->assertSee('Invoice missing');
        $this->get("/admin/support-tickets/{$ticket->id}")->assertOk()->assertSee('My September invoice');
        $this->post("/admin/support-tickets/{$ticket->id}/reply", ['message' => 'Re-issued it now.'])->assertRedirect();
        $this->assertSame(SupportTicket::AWAITING_CLIENT, $ticket->fresh()->status);
        Notification::assertSentTo($agent, SupportTicketNotification::class);

        // Client replies → back in admin queue; then marks solved.
        $this->app['auth']->forgetGuards();
        $this->signIn($agent);
        $this->get('/portal/contact')->assertOk()->assertSee('Awaiting Your Reply');
        $this->post("/portal/contact/tickets/{$ticket->id}/reply", ['message' => 'Got it, thanks'])->assertRedirect();
        $this->assertSame(SupportTicket::OPEN, $ticket->fresh()->status);
        $this->post("/portal/contact/tickets/{$ticket->id}/resolve")->assertRedirect();
        $this->assertSame(SupportTicket::RESOLVED, $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->resolved_at);
        $this->post("/portal/contact/tickets/{$ticket->id}/reopen")->assertRedirect();
        $this->assertSame(SupportTicket::OPEN, $ticket->fresh()->status);

        // Admin closes → client can no longer reply.
        $this->app['auth']->forgetGuards();
        $this->signIn($admin, 'cms');
        $this->put("/admin/support-tickets/{$ticket->id}", ['status' => 'closed', 'priority' => 'urgent'])->assertRedirect();
        $this->assertSame('urgent', $ticket->fresh()->priority);

        $this->app['auth']->forgetGuards();
        $this->signIn($agent);
        $this->post("/portal/contact/tickets/{$ticket->id}/reply", ['message' => 'one more'])->assertRedirect();
        $this->assertSame(SupportTicket::CLOSED, $ticket->fresh()->status);
        $this->get("/portal/contact/tickets/{$ticket->id}")->assertOk()->assertSee('This ticket is closed');

        // Every change is in the thread: 2 client + 1 admin messages (the post-close reply was refused), status/priority notes.
        $this->assertSame(3, $ticket->messages()->where('author_type', '!=', 'system')->count());
        $this->assertGreaterThanOrEqual(5, $ticket->messages()->where('author_type', 'system')->count());
    }

    public function test_issue_type_is_required_and_validated(): void
    {
        $this->signIn($this->independentAgent());

        $this->post('/portal/contact/tickets', ['priority' => 'normal', 'subject' => 'x', 'message' => 'y'])
            ->assertSessionHasErrors('category');
        $this->post('/portal/contact/tickets', ['category' => 'nonsense', 'priority' => 'normal', 'subject' => 'x', 'message' => 'y'])
            ->assertSessionHasErrors('category');
        $this->assertSame(0, SupportTicket::count());
    }

    public function test_clients_cannot_see_each_others_tickets(): void
    {
        Notification::fake();
        $owner = $this->independentAgent();
        $other = $this->agency();

        $this->signIn($owner)->post('/portal/contact/tickets', [
            'category' => 'other', 'priority' => 'normal', 'subject' => 'Private question', 'message' => 'secret',
        ]);
        $ticket = SupportTicket::firstOrFail();

        $this->app['auth']->forgetGuards();
        $this->signIn($other);
        $this->get('/portal/contact')->assertOk()->assertDontSee('Private question');
        $this->get("/portal/contact/tickets/{$ticket->id}")->assertNotFound();
        $this->post("/portal/contact/tickets/{$ticket->id}/reply", ['message' => 'hi'])->assertNotFound();
        $this->post("/portal/contact/tickets/{$ticket->id}/resolve")->assertNotFound();
    }
}
