<?php

namespace App\Http\Controllers\Portal;

use App\Models\CmsKit\SiteInformation;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Contact Us for agents/companies — a support-ticket desk: raise a ticket with an issue type,
 * follow its status, and keep the conversation with MW Realty support in one thread.
 * Every query is scoped to the signed-in portal user's own tickets.
 */
class PortalContactController extends Controller
{
    public function __construct(private readonly SupportTicketService $tickets)
    {
    }

    public function index(Request $request)
    {
        $owner = Auth::guard('portal')->user();
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
            ->paginate(10)
            ->withQueryString();

        $counts = SupportTicket::where('portal_user_id', $owner->id)
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $stats = [
            'total' => $counts->sum(),
            'active' => $counts->only([SupportTicket::OPEN, SupportTicket::IN_PROGRESS, SupportTicket::AWAITING_CLIENT])->sum(),
            'awaiting' => (int) ($counts[SupportTicket::AWAITING_CLIENT] ?? 0),
            'solved' => $counts->only([SupportTicket::RESOLVED, SupportTicket::CLOSED])->sum(),
        ];

        return view('portal.contact.index', [
            'tickets' => $tickets,
            'stats' => $stats,
            'siteInfo' => SiteInformation::first(),
        ]);
    }

    public function create()
    {
        return view('portal.contact.create', ['siteInfo' => SiteInformation::first()]);
    }

    public function store(Request $request)
    {
        $owner = Auth::guard('portal')->user();

        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(SupportTicket::CATEGORIES))],
            'priority' => ['required', Rule::in(array_keys(SupportTicket::PRIORITIES))],
            'subject' => 'required|string|max:150',
            'message' => 'required|string|max:5000',
            'attachment_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ], [], ['category' => 'issue type', 'attachment_document' => 'attachment']);

        $ticket = $this->tickets->create($owner, $data, $request->file('attachment_document'));

        return redirect()->route('portal.contact.show', $ticket)
            ->with('success', "Ticket {$ticket->reference()} has been raised — our team will get back to you shortly.");
    }

    public function show(SupportTicket $ticket)
    {
        $this->authorizeOwner($ticket);
        $ticket->load(['messages.portalUser', 'messages.admin']);

        return view('portal.contact.show', compact('ticket'));
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $owner = $this->authorizeOwner($ticket);
        if ($ticket->isClosed()) {
            return back()->with('error', 'This ticket is closed. Please raise a new ticket if you still need help.');
        }

        $data = $request->validate([
            'message' => 'required|string|max:5000',
            'attachment_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ], [], ['attachment_document' => 'attachment']);

        $this->tickets->clientReply($ticket, $owner, $data['message'], $request->file('attachment_document'));

        return redirect()->route('portal.contact.show', $ticket)->with('success', 'Your reply has been sent.');
    }

    public function resolve(SupportTicket $ticket)
    {
        $this->tickets->clientResolve($ticket, $this->authorizeOwner($ticket));

        return redirect()->route('portal.contact.show', $ticket)->with('success', 'Thanks — the ticket has been marked as solved.');
    }

    public function reopen(SupportTicket $ticket)
    {
        $owner = $this->authorizeOwner($ticket);
        if ($ticket->isClosed()) {
            return back()->with('error', 'This ticket is closed. Please raise a new ticket if you still need help.');
        }
        $this->tickets->clientReopen($ticket, $owner);

        return redirect()->route('portal.contact.show', $ticket)->with('success', 'The ticket has been reopened.');
    }

    public function attachment(SupportTicket $ticket, int $message)
    {
        $this->authorizeOwner($ticket);

        return self::downloadAttachment($ticket, $message);
    }

    /** Shared with the admin controller — private `kyc` disk, only ever from the ticket's own messages. */
    public static function downloadAttachment(SupportTicket $ticket, int $messageId)
    {
        $message = $ticket->messages()->whereKey($messageId)->firstOrFail();
        $path = $message->attachment_path;
        abort_unless($path && str_starts_with($path, SupportTicketService::ATTACHMENT_DIRECTORY . '/') && Storage::disk('kyc')->exists($path), 404);

        return Storage::disk('kyc')->download($path, $message->attachment_name ?: basename($path), [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeOwner(SupportTicket $ticket)
    {
        $owner = Auth::guard('portal')->user();
        abort_unless($ticket->portal_user_id === $owner->id, 404);

        return $owner;
    }
}
