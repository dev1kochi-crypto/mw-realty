<?php

namespace App\Http\Controllers\Crm\Support;

use App\Http\Controllers\Crm\Concerns\ScopesPortalOwner;
use App\Models\CmsKit\SiteInformation;
use App\Models\PortalUser;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Services\SupportTicketService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * @group CRM Contact Us
 *
 * Contact Us for agents/companies — a support-ticket desk: raise a ticket with an issue type,
 * follow its status, and keep the conversation with MW Realty support in one thread.
 * Agent / company accounts only (Super Admin answers tickets in the CMS); every query is scoped
 * to the signed-in account's own tickets, so another account's ticket id is a 404.
 */
class TicketController extends Controller
{
    use ScopesPortalOwner;

    private const CONTACT_FIELDS = [
        'phone_1' => ['fas fa-phone', 'Phone'],
        'whatsapp_number' => ['fab fa-whatsapp', 'WhatsApp'],
        'email_1' => ['fas fa-envelope', 'Email'],
        'address' => ['fas fa-map-marker-alt', 'Office'],
        'working_hours' => ['fas fa-clock', 'Working Hours'],
    ];

    public function __construct(private readonly SupportTicketService $tickets)
    {
    }

    /**
     * Ticket form options & contact details
     *
     * Issue types, priorities and MW Realty's contact details (the "Get in touch" card).
     */
    public function meta()
    {
        $this->client();
        $site = SiteInformation::first();

        return response()->json([
            'categories' => collect(SupportTicket::CATEGORIES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'priorities' => collect(SupportTicket::PRIORITIES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'contact' => collect(self::CONTACT_FIELDS)
                ->filter(fn ($meta, $field) => filled($site?->{$field}))
                ->map(fn ($meta, $field) => ['icon' => $meta[0], 'label' => $meta[1], 'value' => $site->{$field}])
                ->values(),
        ]);
    }

    /**
     * My tickets
     *
     * Ones waiting on the account's reply first, then open ones, then the rest; 10 per page, with counts.
     *
     * @queryParam status string active, or a status: open, in_progress, awaiting_client, resolved, closed. Example: active
     * @queryParam category string An issue type. Example: billing
     * @queryParam search string Subject or ticket number (TKT-00012 / 12). Example: invoice
     * @queryParam page integer Example: 1
     */
    public function index(Request $request)
    {
        $owner = $this->client();
        $status = $request->input('status');
        $filters = ['active', ...array_keys(SupportTicket::STATUSES)];

        $tickets = SupportTicket::where('portal_user_id', $owner->id)
            ->when(in_array($status, $filters, true), fn ($q) => $status === 'active' ? $q->active() : $q->where('status', $status))
            ->when($request->filled('category') && isset(SupportTicket::CATEGORIES[$request->input('category')]),
                fn ($q) => $q->where('category', $request->input('category')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = trim($request->input('search'));
                // "TKT-00012" / "12" also matches by ticket number.
                $id = preg_match('/^(tkt-?)?0*(\d+)$/i', $term, $m) ? (int) $m[2] : 0;
                $q->where(fn ($w) => $w->where('subject', 'like', '%' . $term . '%')->when($id > 0, fn ($w) => $w->orWhere('id', $id)));
            })
            ->withCount(['messages as replies_count' => fn ($q) => $q->where('author_type', '!=', 'system')])
            ->orderByRaw("CASE WHEN status = 'awaiting_client' THEN 0 WHEN status IN ('open','in_progress') THEN 1 ELSE 2 END")
            ->latest('last_reply_at')
            ->paginate(10);

        $counts = SupportTicket::where('portal_user_id', $owner->id)
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'data' => collect($tickets->items())->map(fn (SupportTicket $ticket) => $this->ticketJson($ticket) + [
                'replies_count' => (int) $ticket->replies_count,
            ]),
            'meta' => [
                'current_page' => $tickets->currentPage(), 'last_page' => $tickets->lastPage(), 'total' => $tickets->total(),
                'from' => $tickets->firstItem(), 'to' => $tickets->lastItem(),
            ],
            'stats' => [
                'total' => (int) $counts->sum(),
                'active' => (int) $counts->only([SupportTicket::OPEN, SupportTicket::IN_PROGRESS, SupportTicket::AWAITING_CLIENT])->sum(),
                'awaiting' => (int) ($counts[SupportTicket::AWAITING_CLIENT] ?? 0),
                'solved' => (int) $counts->only([SupportTicket::RESOLVED, SupportTicket::CLOSED])->sum(),
            ],
        ]);
    }

    /**
     * Raise a ticket
     *
     * Multipart when attaching a file.
     *
     * @bodyParam category string required An issue type. Example: billing
     * @bodyParam priority string required low, normal, high or urgent. Example: normal
     * @bodyParam subject string required Max 150. Example: Invoice missing
     * @bodyParam message string required Max 5000. Example: I can't find last month's invoice.
     * @bodyParam attachment_document file JPG, PNG or PDF, max 4 MB.
     */
    public function store(Request $request)
    {
        $owner = $this->client();

        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(SupportTicket::CATEGORIES))],
            'priority' => ['required', Rule::in(array_keys(SupportTicket::PRIORITIES))],
            'subject' => 'required|string|max:150',
            'message' => 'required|string|max:5000',
            'attachment_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ], [], ['category' => 'issue type', 'attachment_document' => 'attachment']);

        $ticket = $this->tickets->create($owner, $data, $request->file('attachment_document'));

        return response()->json([
            'success' => true,
            'message' => "Ticket {$ticket->reference()} has been raised — our team will get back to you shortly.",
            'id' => $ticket->id,
        ], 201);
    }

    /** One ticket with its conversation */
    public function show($ticket)
    {
        $ticket = $this->find($ticket);
        $ticket->load(['messages.portalUser', 'messages.admin']);

        return response()->json($this->detailJson($ticket));
    }

    /**
     * Reply
     *
     * Replying to a solved ticket reopens it; a closed ticket can't take replies (422).
     *
     * @bodyParam message string required Max 5000. Example: Thanks, that fixed it.
     * @bodyParam attachment_document file JPG, PNG or PDF, max 4 MB.
     */
    public function reply(Request $request, $ticket)
    {
        $ticket = $this->find($ticket);
        if ($ticket->isClosed()) {
            return $this->closed();
        }

        $data = $request->validate([
            'message' => 'required|string|max:5000',
            'attachment_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ], [], ['attachment_document' => 'attachment']);

        $this->tickets->clientReply($ticket, $this->client(), $data['message'], $request->file('attachment_document'));

        return $this->done($ticket, 'Your reply has been sent.');
    }

    /** Mark as solved */
    public function resolve($ticket)
    {
        $ticket = $this->find($ticket);
        $this->tickets->clientResolve($ticket, $this->client());

        return $this->done($ticket, 'Thanks — the ticket has been marked as solved.');
    }

    /** Reopen a solved ticket */
    public function reopen($ticket)
    {
        $ticket = $this->find($ticket);
        if ($ticket->isClosed()) {
            return $this->closed();
        }
        $this->tickets->clientReopen($ticket, $this->client());

        return $this->done($ticket, 'The ticket has been reopened.');
    }

    /** Download a message's attachment */
    public function attachment($ticket, int $message)
    {
        return $this->tickets->downloadAttachment($this->find($ticket), $message);
    }

    /** The signed-in agent / company — Super Admin has no tickets of its own. */
    private function client(): PortalUser
    {
        $owner = $this->owner();
        abort_unless($owner, 403, 'Support tickets are raised by agent and agency accounts.');

        return $owner;
    }

    private function find($id): SupportTicket
    {
        return SupportTicket::where('portal_user_id', $this->client()->id)->findOrFail((int) $id);
    }

    private function done(SupportTicket $ticket, string $message)
    {
        $ticket = $ticket->fresh(['messages.portalUser', 'messages.admin']);

        return response()->json(['success' => true, 'message' => $message, 'ticket' => $this->detailJson($ticket)]);
    }

    private function closed()
    {
        $message = 'This ticket is closed. Please raise a new ticket if you still need help.';

        return response()->json(['message' => $message, 'errors' => ['message' => [$message]]], 422);
    }

    private function ticketJson(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'reference' => $ticket->reference(),
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'status_label' => $ticket->statusLabel(),
            'status_tone' => $ticket->statusTone(),
            'category_label' => $ticket->categoryLabel(),
            'priority' => $ticket->priority,
            'priority_label' => $ticket->priorityLabel(),
            'updated_at' => ($ticket->last_reply_at ?? $ticket->updated_at)?->toIso8601String(),
        ];
    }

    private function detailJson(SupportTicket $ticket): array
    {
        return $this->ticketJson($ticket) + [
            'created_at' => $ticket->created_at?->toIso8601String(),
            'resolved_at' => $ticket->resolved_at?->toIso8601String(),
            'is_closed' => $ticket->isClosed(),
            'is_solved' => $ticket->isSolved(),
            'messages' => $ticket->messages->map(fn (SupportTicketMessage $message) => [
                'id' => $message->id,
                'system' => $message->isSystem(),
                'mine' => $message->author_type === SupportTicketMessage::CLIENT,
                'author' => $message->authorName(),
                'body' => $message->body,
                'created_at' => $message->created_at?->toIso8601String(),
                'attachment_name' => $message->attachment_path ? ($message->attachment_name ?: 'Attachment') : null,
            ])->values(),
        ];
    }
}
