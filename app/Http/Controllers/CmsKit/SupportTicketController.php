<?php

namespace App\Http\Controllers\CmsKit;

use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/** Admin side of portal Contact Us tickets — queue, conversation, reply and status/priority changes. */
class SupportTicketController extends Controller
{
    public function __construct(private readonly SupportTicketService $tickets)
    {
    }

    public function index(Request $request)
    {
        $status = $request->input('status', 'active');

        $tickets = SupportTicket::with('portalUser:id,type,name,company_name,email')
            ->when($status === 'active', fn ($q) => $q->active())
            ->when($status === 'needs_reply', fn ($q) => $q->needsAdminReply())
            ->when(isset(SupportTicket::STATUSES[$status]), fn ($q) => $q->where('status', $status))
            ->when(isset(SupportTicket::CATEGORIES[$request->input('category')]), fn ($q) => $q->where('category', $request->input('category')))
            ->when(isset(SupportTicket::PRIORITIES[$request->input('priority')]), fn ($q) => $q->where('priority', $request->input('priority')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = trim($request->input('search'));
                $id = preg_match('/^(tkt-?)?0*(\d+)$/i', $term, $m) ? (int) $m[2] : 0;
                $q->where(fn ($w) => $w->where('subject', 'like', "%{$term}%")
                    ->orWhereHas('portalUser', fn ($u) => $u->where('name', 'like', "%{$term}%")
                        ->orWhere('company_name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%"))
                    ->when($id > 0, fn ($w) => $w->orWhere('id', $id)));
            })
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 ELSE 2 END")
            ->latest('last_reply_at')
            ->paginate(20)
            ->withQueryString();

        $counts = SupportTicket::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $stats = [
            'needs_reply' => SupportTicket::needsAdminReply()->count(),
            'active' => $counts->only([SupportTicket::OPEN, SupportTicket::IN_PROGRESS, SupportTicket::AWAITING_CLIENT])->sum(),
            'awaiting' => (int) ($counts[SupportTicket::AWAITING_CLIENT] ?? 0),
            'solved' => $counts->only([SupportTicket::RESOLVED, SupportTicket::CLOSED])->sum(),
        ];

        return view('cms-kit::support-tickets.index', compact('tickets', 'stats', 'status'));
    }

    public function show(SupportTicket $ticket)
    {
        $ticket->load(['portalUser', 'messages.portalUser', 'messages.admin']);
        $otherTickets = SupportTicket::where('portal_user_id', $ticket->portal_user_id)
            ->whereKeyNot($ticket->id)->latest()->take(5)->get();

        return view('cms-kit::support-tickets.show', compact('ticket', 'otherTickets'));
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate([
            'message' => 'required|string|max:5000',
            'status' => ['nullable', Rule::in(array_keys(SupportTicket::STATUSES))],
            'attachment_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ], [], ['attachment_document' => 'attachment']);

        $this->tickets->adminReply($ticket, Auth::guard('cms')->user(), $data['message'], $request->file('attachment_document'), $data['status'] ?? null);

        return redirect()->route('cms.support-tickets.show', $ticket)->with('success', 'Reply sent to the client.');
    }

    public function update(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(SupportTicket::STATUSES))],
            'priority' => ['required', Rule::in(array_keys(SupportTicket::PRIORITIES))],
        ]);

        $this->tickets->adminUpdate($ticket, Auth::guard('cms')->user(), $data['status'], $data['priority']);

        return redirect()->route('cms.support-tickets.show', $ticket)->with('success', 'Ticket updated.');
    }

    public function attachment(SupportTicket $ticket, int $message)
    {
        return $this->tickets->downloadAttachment($ticket, $message);
    }
}
