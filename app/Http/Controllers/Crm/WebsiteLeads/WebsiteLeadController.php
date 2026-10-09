<?php

namespace App\Http\Controllers\Crm\WebsiteLeads;

use App\Http\Controllers\Crm\Concerns\ScopesPortalOwner;
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
 * @group CRM Website Leads
 *
 * Super Admin only (web app, CMS session): everyone who identified themselves on the website — AI
 * chat, contact / landing page / enquiry forms, customer accounts — with their tracked activity.
 * "Lead Pool" = not routed to any agency / agent yet (they never opened a listing that has one);
 * Super Admin transfers those — one, a selection, or every lead matching the filters. See VisitorTracker.
 */
class WebsiteLeadController extends Controller
{
    use ScopesPortalOwner;

    public const STATUSES = ['pool' => 'Lead Pool', 'routed' => 'Routed Leads', 'all' => 'All Website Leads'];
    /** "Select all matching" transfers at most this many in one go. */
    private const BULK_LIMIT = 500;

    private function authorizeAdmin(): void
    {
        abort_unless($this->isAdmin(), 403);
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

    /**
     * List website leads
     *
     * 20 per page, most recently active first, with the tab counts.
     *
     * @queryParam status string pool | routed | all. Example: pool
     * @queryParam source string One of `sources` keys. Example: ai-chat
     * @queryParam q string Name, email or phone. Example: sara
     * @queryParam page integer Example: 1
     */
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
            ->paginate(20);

        return response()->json([
            'data' => collect($leads->items())->map(fn (VisitorLead $lead) => [
                'id' => $lead->id,
                'name' => $lead->displayName(),
                'email' => $lead->email,
                'formatted_phone' => $lead->formattedPhone(),
                'source' => $lead->source,
                'source_label' => $lead->sourceLabel(),
                'has_account' => (bool) $lead->user_id,
                'property_views' => (int) $lead->property_views_count,
                'site_seconds' => (int) $lead->site_seconds,
                'chats' => (int) $lead->chats_count,
                'routed_to' => $lead->crmLeads->map(fn ($crmLead) => $crmLead->owner?->displayName())->values(),
                'last_seen_at' => $lead->last_seen_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $leads->currentPage(),
                'last_page' => $leads->lastPage(),
                'total' => $leads->total(),
                'from' => $leads->firstItem(),
                'to' => $leads->lastItem(),
            ],
            'filters' => $filters,
            'statuses' => self::STATUSES,
            'sources' => VisitorLead::SOURCE_LABELS,
            'counts' => [
                'pool' => VisitorLead::inPool()->count(),
                'routed' => VisitorLead::routed()->count(),
                'all' => VisitorLead::count(),
            ],
            'bulk_limit' => self::BULK_LIMIT,
        ]);
    }

    /**
     * Website lead profile
     *
     * Who they are, where they were routed, most interested properties, and the previous / next
     * lead in the listing's order. Their tracked activity loads from `/website-leads/{id}/insights/summary`.
     */
    public function show(VisitorLead $websiteLead, VisitorInsights $insights)
    {
        $this->authorizeAdmin();
        $data = $insights->for($websiteLead);
        $lead = $websiteLead;

        // Previous / next in the listing's order (most recently active first).
        $seen = $lead->last_seen_at;
        $newer = VisitorLead::whereKeyNot($lead->id)
            ->where(fn ($q) => $q->where('last_seen_at', '>', $seen)->orWhere(fn ($s) => $s->where('last_seen_at', $seen)->where('id', '>', $lead->id)))
            ->orderBy('last_seen_at')->orderBy('id')->value('id');
        $older = VisitorLead::whereKeyNot($lead->id)
            ->where(fn ($q) => $q->where('last_seen_at', '<', $seen)->orWhereNull('last_seen_at')->orWhere(fn ($s) => $s->where('last_seen_at', $seen)->where('id', '<', $lead->id)))
            ->orderByDesc('last_seen_at')->orderByDesc('id')->value('id');

        $stats = $data['stats'];

        return response()->json([
            'lead' => [
                'id' => $lead->id,
                'name' => $lead->name,
                'display_name' => $lead->displayName(),
                'email' => $lead->email,
                'formatted_phone' => $lead->formattedPhone(),
                'source_label' => $lead->sourceLabel(),
                'has_account' => (bool) $lead->user_id,
            ],
            'stats' => array_merge($stats, [
                'first_seen' => $stats['first_seen'] ? \Illuminate\Support\Carbon::parse($stats['first_seen'])->toIso8601String() : null,
                'last_seen' => $stats['last_seen'] ? \Illuminate\Support\Carbon::parse($stats['last_seen'])->toIso8601String() : null,
            ]),
            'crm_leads' => $data['crmLeads']->map(fn ($crmLead) => [
                'id' => $crmLead->id,
                'routed' => $crmLead->portal_user_id !== null,
                'owner' => $crmLead->owner?->displayName(),
                'agent' => $crmLead->agent?->name,
                'property' => $crmLead->property?->getTranslation('title'),
                'created_at' => $crmLead->created_at?->toIso8601String(),
            ])->values(),
            'top_properties' => $data['topProperties']->take(3)->map(fn ($row) => [
                'title' => $row['property']->getTranslation('title') ?: $row['property']->reference_no,
                'url' => url('/property-details/' . $row['property']->slug),
                'owner' => $row['property']->owner?->displayName() ?? 'MW Realty',
                'views' => $row['views'],
                'seconds' => $row['seconds'],
            ])->values(),
            'previous_lead_id' => $newer,
            'next_lead_id' => $older,
        ]);
    }

    /**
     * Insights panel
     *
     * Stats, engagement, most interested properties and the first page of each list (VisitorInsights::summaryJson).
     */
    public function insightsSummary(VisitorLead $websiteLead, VisitorInsights $insights)
    {
        $this->authorizeAdmin();

        return response()->json($insights->summaryJson($websiteLead));
    }

    /**
     * Insights panel — next page of a list
     *
     * The next page of its timeline / favorites / saved searches / chats (load on scroll).
     *
     * @queryParam section string required timeline | favorites | searches | chats | messages. Example: timeline
     * @queryParam after integer The last id already shown. Example: 120
     */
    public function insights(Request $request, VisitorLead $websiteLead, VisitorInsights $insights)
    {
        $this->authorizeAdmin();

        return response()->json($insights->feedJson($websiteLead, $request));
    }

    /**
     * Transfer website leads
     *
     * One lead, the ticked ones, or (all=1) every lead matching the listing's filters (sent alongside).
     *
     * @bodyParam portal_user_id integer required The agency / agent. Example: 12
     * @bodyParam note string Example: Interested in 2BR in Marina
     * @bodyParam ids integer[] Example: [5, 6]
     * @bodyParam all boolean Example: false
     * @bodyParam exclude integer[] With all, leads unticked afterwards. Example: []
     */
    public function transfer(Request $request, VisitorTracker $tracker)
    {
        $this->authorizeAdmin();
        $data = $request->validate([
            'portal_user_id' => 'required|integer',
            'note' => 'nullable|string|max:1000',
            'all' => 'nullable|boolean',
            'ids' => 'required_unless:all,1,true|array|max:' . self::BULK_LIMIT,
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

        return response()->json([
            'success' => true,
            'message' => ($leads->count() === 1 ? '1 lead' : "{$leads->count()} leads") . " transferred to {$target->displayName()}.",
            'count' => $leads->count(),
        ]);
    }

    /**
     * Transfer targets
     *
     * Agencies and agents, searched and paged on the server (20 at a time).
     *
     * @queryParam q string Example: prime
     * @queryParam page integer Example: 1
     */
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
            'data' => collect($page->items())->map(fn (PortalUser $user) => [
                'id' => $user->id,
                'name' => $user->displayName(),
                'meta' => $user->isAgency() ? 'Agency' : ($user->company ? 'Agent · ' . $user->company->displayName() : 'Independent agent'),
            ]),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ]);
    }
}
