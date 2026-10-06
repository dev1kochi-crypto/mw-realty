<?php

namespace App\Http\Controllers\Portal\Crm;

use App\Models\PortalUser;
use App\Models\Visitors\VisitorEvent;
use App\Models\Visitors\VisitorLead;
use App\Services\Visitors\VisitorInsights;
use App\Services\Visitors\VisitorTracker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * CRM › Website Leads (Super Admin only): everyone who identified themselves on the website — AI
 * chat, contact / landing page / enquiry forms, customer accounts — with their tracked activity.
 * "Lead Pool" = not routed to any agency / agent yet (they never opened a listing that has one);
 * Super Admin transfers those — one, a selection, or every lead matching the filters. See VisitorTracker.
 */
class WebsiteLeadController extends Controller
{
    public const STATUSES = ['pool' => 'Lead Pool', 'routed' => 'Routed Leads', 'all' => 'All Website Leads'];
    /** "Select all matching" transfers at most this many in one go. */
    private const BULK_LIMIT = 500;

    private function authorizeAdmin(): void
    {
        abort_unless(!Auth::guard('portal')->check() && Auth::guard('cms')->user()?->hasRole('superadmin'), 403);
    }

    /** status / source / q from the request (the listing's filters), normalised. */
    private function filters(Request $request): array
    {
        return [
            'status' => array_key_exists($request->input('status'), self::STATUSES) ? $request->input('status') : 'pool',
            'source' => array_key_exists($request->input('source'), VisitorLead::SOURCE_LABELS) ? $request->input('source') : null,
            'search' => mb_substr(trim((string) $request->input('q', '')), 0, 100),
        ];
    }

    private function filteredQuery(array $filters): Builder
    {
        $digits = preg_replace('/\D+/', '', $filters['search']);

        return VisitorLead::query()
            ->when($filters['status'] === 'pool', fn ($q) => $q->inPool())
            ->when($filters['status'] === 'routed', fn ($q) => $q->routed())
            ->when($filters['source'], fn ($q) => $q->where('source', $filters['source']))
            ->when($filters['search'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$filters['search']}%")
                ->orWhere('email', 'like', "%{$filters['search']}%")
                ->when(strlen($digits) >= 4, fn ($p) => $p->orWhere('phone', 'like', "%{$digits}%"))))
            ->orderByDesc('last_seen_at')->orderByDesc('id');
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin();
        $filters = $this->filters($request);

        $leads = $this->filteredQuery($filters)
            ->withCount([
                'events as property_views_count' => fn ($q) => $q->where('type', VisitorEvent::PROPERTY_VIEW),
                'conversations as chats_count' => fn ($q) => $q->where('message_count', '>', 0),
            ])
            ->withSum('events as site_seconds', 'duration_seconds')
            ->with(['crmLeads' => fn ($q) => $q->whereNotNull('portal_user_id')->with('owner')])
            ->paginate(20)->withQueryString();

        return view('portal.crm.website-leads.index', $filters + [
            'leads' => $leads,
            'statuses' => self::STATUSES,
            'counts' => [
                'pool' => VisitorLead::inPool()->count(),
                'routed' => VisitorLead::routed()->count(),
                'all' => VisitorLead::count(),
            ],
            'bulkLimit' => self::BULK_LIMIT,
        ]);
    }

    public function show(VisitorLead $websiteLead, VisitorInsights $insights)
    {
        $this->authorizeAdmin();

        // Previous / next in the listing's order (most recently active first).
        $seen = $websiteLead->last_seen_at;
        $newer = VisitorLead::whereKeyNot($websiteLead->id)
            ->where(fn ($q) => $q->where('last_seen_at', '>', $seen)->orWhere(fn ($s) => $s->where('last_seen_at', $seen)->where('id', '>', $websiteLead->id)))
            ->orderBy('last_seen_at')->orderBy('id')->value('id');
        $older = VisitorLead::whereKeyNot($websiteLead->id)
            ->where(fn ($q) => $q->where('last_seen_at', '<', $seen)->orWhereNull('last_seen_at')->orWhere(fn ($s) => $s->where('last_seen_at', $seen)->where('id', '<', $websiteLead->id)))
            ->orderByDesc('last_seen_at')->orderByDesc('id')->value('id');

        return view('portal.crm.website-leads.show', $insights->for($websiteLead) + [
            'previousLeadId' => $newer,
            'nextLeadId' => $older,
            'feedUrl' => route('portal.crm.website-leads.insights', $websiteLead),
        ]);
    }

    /** Insights panel — the next page of its timeline / favorites / saved searches / chats (load on scroll). */
    public function insights(Request $request, VisitorLead $websiteLead, VisitorInsights $insights)
    {
        $this->authorizeAdmin();

        return response()->json($insights->feed($websiteLead, $request));
    }

    /** Transfer one lead, the ticked ones, or (all=1) every lead matching the listing's filters. */
    public function transfer(Request $request, VisitorTracker $tracker)
    {
        $this->authorizeAdmin();
        $data = $request->validate([
            'portal_user_id' => 'required|integer',
            'note' => 'nullable|string|max:1000',
            'all' => 'nullable|boolean',
            'ids' => 'required_unless:all,1|array|max:' . self::BULK_LIMIT,
            'ids.*' => 'integer',
            'exclude' => 'nullable|array',
            'exclude.*' => 'integer',
        ]);

        $target = PortalUser::approved()->where('is_active', true)->find($data['portal_user_id']);
        if (!$target) {
            throw ValidationException::withMessages(['portal_user_id' => 'Choose an active, approved agency or agent.']);
        }

        $leads = $request->boolean('all')
            ? $this->filteredQuery($this->filters($request))->whereKeyNot($data['exclude'] ?? [])->limit(self::BULK_LIMIT)->get()
            : VisitorLead::whereKey($data['ids'])->get();

        $admin = Auth::guard('cms')->user();
        foreach ($leads as $lead) {
            $tracker->transfer($lead, $target, $data['note'] ?? null, $admin?->id, $admin?->name);
        }

        $message = ($leads->count() === 1 ? '1 lead' : "{$leads->count()} leads") . " transferred to {$target->displayName()}.";

        return $leads->count() === 1 && $request->input('from') === 'profile'
            ? redirect()->route('portal.crm.website-leads.show', $leads->first())->with('success', $message)
            : redirect()->back()->with('success', $message);
    }

    /** Transfer picker — agencies and agents, searched and paged on the server (20 at a time). */
    public function targets(Request $request)
    {
        $this->authorizeAdmin();
        $term = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        $page = PortalUser::approved()->where('is_active', true)->with('company:id,company_name,name,type')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")
                ->orWhere('company_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->orderByRaw("CASE WHEN type = 'company' THEN 0 ELSE 1 END")
            ->orderByRaw('COALESCE(company_name, name)')
            ->paginate(20, ['id', 'name', 'company_name', 'type', 'company_id', 'email']);

        return response()->json([
            'results' => collect($page->items())->map(fn (PortalUser $user) => [
                'id' => $user->id,
                'text' => $user->displayName(),
                'meta' => $user->isAgency() ? 'Agency' : ($user->company ? 'Agent · ' . $user->company->displayName() : 'Independent agent'),
            ]),
            'pagination' => ['more' => $page->hasMorePages()],
        ]);
    }
}
