<?php

namespace App\Services;

use App\Models\CmsKit\Admin;
use App\Models\PortalUser;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Notifications\SupportTicketNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Support ticket lifecycle:
 *
 *   client raises ─→ open ─→ in_progress ─→ awaiting_client ⇄ (client replies → open)
 *                      └──────────┴──────────────┴─→ resolved ─(client replies / reopens)─→ open
 *                                                        └─→ closed (final — raise a new ticket)
 *
 * Every reply and status change is appended to the ticket's thread, so the thread is the history.
 */
class SupportTicketService
{
    public const ATTACHMENT_DIRECTORY = 'support-tickets';

    public function __construct(private readonly ManagedFiles $files)
    {
    }

    public function create(PortalUser $client, array $data, ?UploadedFile $attachment): SupportTicket
    {
        $ticket = SupportTicket::create([
            'portal_user_id' => $client->id,
            'category' => $data['category'],
            'priority' => $data['priority'] ?? 'normal',
            'subject' => $data['subject'],
            'status' => SupportTicket::OPEN,
            'last_reply_by' => SupportTicketMessage::CLIENT,
            'last_reply_at' => now(),
        ]);

        $this->addMessage($ticket, SupportTicketMessage::CLIENT, $data['message'], $attachment, portalUser: $client);

        $this->notifyAdmins(
            $ticket,
            'New support ticket',
            "{$client->displayName()} raised {$ticket->reference()}: {$ticket->subject}",
        );

        return $ticket;
    }

    /** A client reply always puts the ticket back in the admin queue (reopening it if it was solved). */
    public function clientReply(SupportTicket $ticket, PortalUser $client, string $body, ?UploadedFile $attachment): void
    {
        abort_if($ticket->isClosed(), 422, 'This ticket is closed. Please raise a new ticket.');

        $this->addMessage($ticket, SupportTicketMessage::CLIENT, $body, $attachment, portalUser: $client);

        if ($ticket->status !== SupportTicket::OPEN && $ticket->status !== SupportTicket::IN_PROGRESS) {
            $this->setStatus($ticket, SupportTicket::OPEN, $client->displayName(), notifyClient: false);
        }
        $ticket->update(['last_reply_by' => SupportTicketMessage::CLIENT, 'last_reply_at' => now()]);

        $this->notifyAdmins($ticket, 'Client replied', "{$client->displayName()} replied on {$ticket->reference()}: {$ticket->subject}");
    }

    /**
     * Admin reply, optionally moving the status in the same step. With no explicit status an
     * open ticket moves to "awaiting client", since the ball is now in their court.
     */
    public function adminReply(SupportTicket $ticket, Admin $admin, string $body, ?UploadedFile $attachment, ?string $status): void
    {
        $this->addMessage($ticket, SupportTicketMessage::ADMIN, $body, $attachment, admin: $admin);
        $ticket->update(['last_reply_by' => SupportTicketMessage::ADMIN, 'last_reply_at' => now()]);

        $status ??= in_array($ticket->status, [SupportTicket::OPEN, SupportTicket::IN_PROGRESS], true)
            ? SupportTicket::AWAITING_CLIENT
            : $ticket->status;
        if ($status !== $ticket->status) {
            $this->setStatus($ticket, $status, $admin->name, notifyClient: false);
        }

        $this->notifyClient(
            $ticket,
            'Support replied to your ticket',
            "{$ticket->reference()}: {$ticket->subject} — " . Str::limit($body, 90),
            'fa-reply',
        );
    }

    public function adminUpdate(SupportTicket $ticket, Admin $admin, string $status, string $priority): void
    {
        if ($priority !== $ticket->priority) {
            $from = $ticket->priorityLabel();
            $ticket->update(['priority' => $priority]);
            $this->addMessage($ticket, SupportTicketMessage::SYSTEM, "Priority changed from {$from} to {$ticket->priorityLabel()} by {$admin->name}.");
        }
        if ($status !== $ticket->status) {
            $this->setStatus($ticket, $status, $admin->name);
        }
    }

    /** Client marks their own ticket solved. */
    public function clientResolve(SupportTicket $ticket, PortalUser $client): void
    {
        if (!$ticket->isSolved()) {
            $this->setStatus($ticket, SupportTicket::RESOLVED, $client->displayName(), notifyClient: false);
            $this->notifyAdmins($ticket, 'Ticket marked solved', "{$client->displayName()} marked {$ticket->reference()} as solved.", 'fa-check-circle', 'green');
        }
    }

    public function clientReopen(SupportTicket $ticket, PortalUser $client): void
    {
        abort_if($ticket->isClosed(), 422, 'This ticket is closed. Please raise a new ticket.');

        if ($ticket->status === SupportTicket::RESOLVED) {
            $this->setStatus($ticket, SupportTicket::OPEN, $client->displayName(), notifyClient: false);
            $ticket->update(['last_reply_by' => SupportTicketMessage::CLIENT, 'last_reply_at' => now()]);
            $this->notifyAdmins($ticket, 'Ticket reopened', "{$client->displayName()} reopened {$ticket->reference()}: {$ticket->subject}");
        }
    }

    private function setStatus(SupportTicket $ticket, string $status, string $actorName, bool $notifyClient = true): void
    {
        $from = $ticket->statusLabel(true);
        $ticket->update([
            'status' => $status,
            'resolved_at' => $status === SupportTicket::RESOLVED ? now() : ($status === SupportTicket::CLOSED ? $ticket->resolved_at : null),
            'closed_at' => $status === SupportTicket::CLOSED ? now() : null,
        ]);
        $this->addMessage($ticket, SupportTicketMessage::SYSTEM, "Status changed from {$from} to {$ticket->statusLabel(true)} by {$actorName}.");

        if ($notifyClient) {
            $this->notifyClient(
                $ticket,
                'Ticket ' . strtolower($ticket->statusLabel()),
                "{$ticket->reference()}: {$ticket->subject} is now {$ticket->statusLabel()}.",
                $ticket->isSolved() ? 'fa-check-circle' : 'fa-headset',
                $ticket->isSolved() ? 'green' : 'teal',
            );
        }
    }

    private function addMessage(
        SupportTicket $ticket,
        string $authorType,
        string $body,
        ?UploadedFile $attachment = null,
        ?PortalUser $portalUser = null,
        ?Admin $admin = null,
    ): SupportTicketMessage {
        return $ticket->messages()->create([
            'author_type' => $authorType,
            'portal_user_id' => $portalUser?->id,
            'admin_id' => $admin?->id,
            'body' => $body,
            'attachment_path' => $attachment ? $this->files->store($attachment, self::ATTACHMENT_DIRECTORY, 'kyc') : null,
            'attachment_name' => $attachment ? Str::limit($attachment->getClientOriginalName(), 180, '') : null,
        ]);
    }

    private function notifyClient(SupportTicket $ticket, string $title, string $message, string $icon = 'fa-headset', string $tone = 'teal'): void
    {
        try {
            $ticket->portalUser?->notify(new SupportTicketNotification($title, $message, route('portal.contact.show', $ticket), $icon, $tone));
        } catch (\Throwable) {
            // Non-fatal — the ticket change itself already succeeded.
        }
    }

    private function notifyAdmins(SupportTicket $ticket, string $title, string $message, string $icon = 'fa-headset', string $tone = 'teal'): void
    {
        try {
            $notification = new SupportTicketNotification($title, $message, route('cms.support-tickets.show', $ticket), $icon, $tone);
            Admin::where('is_active', true)->get()
                ->filter(fn (Admin $admin) => $admin->hasRole('superadmin') || $admin->can('support-tickets.view'))
                ->each(fn (Admin $admin) => $admin->notify($notification));
        } catch (\Throwable) {
            // Non-fatal — same as above.
        }
    }
}
