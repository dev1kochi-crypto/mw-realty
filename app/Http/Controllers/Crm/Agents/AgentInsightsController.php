<?php

namespace App\Http\Controllers\Crm\Agents;

use App\Http\Controllers\Crm\Concerns\ScopesPortalOwner;
use App\Models\AgencyAgent;
use App\Models\LeadFollowUp;
use App\Models\PortalUser;
use App\Services\Agency\AgentInsightsService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;

/**
 * @group CRM Agent Insights
 *
 * How well an agency's agents handle the leads and listings assigned to them: response rate and
 * time, untouched leads, overdue follow-ups, listing quality and live listings, and an Agent score
 * out of 100 (AgentInsightsService). Agency accounts see their own current agents; Super Admin sees
 * every agency's. Plan feature `agent_insights`.
 */
class AgentInsightsController extends Controller
{
    use ScopesPortalOwner;

    public function __construct(private readonly AgentInsightsService $insights)
    {
    }

    /**
     * Agent insights
     *
     * 20 agents per page with their numbers and score, plus team totals.
     *
     * @queryParam days integer 7, 30 or 90 — the period for leads received / closed. Example: 30
     * @queryParam sort string attention or name. Example: attention
     * @queryParam search string Agent name or email. Example: omar
     * @queryParam page integer Example: 1
     */
    public function index(Request $request)
    {
        $agency = $this->agency();
        $days = $this->days($request);
        $sort = array_key_exists((string) $request->query('sort'), AgentInsightsService::SORTS) ? (string) $request->query('sort') : 'attention';
        $search = mb_substr(trim((string) $request->query('search', '')), 0, 100);

        $agents = $this->insights->agentsQuery($agency)
            ->with(['company', 'currentMembership'])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('portal_users.name', 'like', "%{$search}%")
                ->orWhere('portal_users.email', 'like', "%{$search}%")))
            ->when($sort === 'attention', fn ($q) => $this->insights->orderByAttention($q))
            ->orderBy('portal_users.name')
            ->paginate(20);

        $metrics = $this->insights->forAgents(collect($agents->items()), $days);

        return response()->json([
            'data' => collect($agents->items())->map(fn (PortalUser $agent) => $this->agentJson($agent) + $metrics[$agent->id]),
            'meta' => $this->meta($agents),
            'totals' => $this->insights->totals($agency, $days),
            'days' => $days,
            'periods' => $this->options(AgentInsightsService::PERIODS),
            'sorts' => $this->options(AgentInsightsService::SORTS),
            'response_hours' => AgentInsightsService::RESPONSE_HOURS,
            'is_admin' => $this->isAdmin(),
        ]);
    }

    /**
     * One agent's insights
     *
     * The numbers and score breakdown, plus what needs their attention: untouched leads, overdue
     * follow-ups and listings needing work (10 per page each).
     *
     * @queryParam days integer 7, 30 or 90. Example: 30
     * @queryParam leads_page integer Example: 1
     * @queryParam follow_ups_page integer Example: 1
     * @queryParam listings_page integer Example: 1
     */
    public function show(Request $request, int $id)
    {
        $agent = $this->insights->agentsQuery($this->agency())->with(['company', 'currentMembership'])->findOrFail($id);
        $days = $this->days($request);

        $leads = $this->insights->untouchedLeadsOf($agent)->paginate(10, ['*'], 'leads_page');
        $followUps = $this->insights->overdueFollowUpsOf($agent)->paginate(10, ['*'], 'follow_ups_page');
        $listingRows = $this->insights->listingsNeedingWorkOf($agent);
        $listingsPage = max(1, $request->integer('listings_page', 1));
        $listings = new LengthAwarePaginator($listingRows->forPage($listingsPage, 10)->values(), $listingRows->count(), 10, $listingsPage);

        return response()->json([
            'agent' => $this->agentJson($agent) + [
                'phone' => $agent->phone,
                'joined_at' => $agent->currentMembership?->joined_at?->toIso8601String(),
                // The Agents roster's detail page is per membership (agency only — Super Admin has no roster page).
                'membership_id' => $this->isAdmin() ? null : $agent->currentMembership?->id,
            ] + $this->insights->forAgents(collect([$agent]), $days)[$agent->id],
            'untouched_leads' => [
                'data' => collect($leads->items())->map(fn ($lead) => [
                    'id' => $lead->id,
                    'name' => $lead->name,
                    'contact' => $lead->email ?: $lead->formatted_phone,
                    'stage' => $lead->stage ? ['name' => $lead->stage->name, 'color' => $lead->stage->color] : null,
                    'received_at' => \Illuminate\Support\Carbon::parse($lead->received_at)->toIso8601String(),
                ]),
                'meta' => $this->meta($leads),
            ],
            'overdue_follow_ups' => [
                'data' => collect($followUps->items())->map(fn (LeadFollowUp $f) => [
                    'id' => $f->id,
                    'lead_id' => $f->lead_id,
                    'lead_name' => $f->lead_name,
                    'type' => LeadFollowUp::TYPES[$f->type] ?? ucfirst($f->type),
                    'note' => $f->note,
                    'due_at' => $f->due_at?->toIso8601String(),
                ]),
                'meta' => $this->meta($followUps),
            ],
            'listings' => [
                'data' => collect($listings->items())->map(fn ($row) => [
                    'id' => $row['property']->id,
                    'reference_no' => $row['property']->reference_no,
                    'title' => $row['property']->getTranslation('title'),
                    'segment' => $row['property']->category === 'commercial' ? 'commercial' : 'residential',
                    'quality' => ['score' => $row['quality']['score'], 'tone' => $row['quality']['tone']],
                    'issues' => $row['issues'],
                ]),
                'meta' => $this->meta($listings),
            ],
            'days' => $days,
            'periods' => $this->options(AgentInsightsService::PERIODS),
            'response_hours' => AgentInsightsService::RESPONSE_HOURS,
            'is_admin' => $this->isAdmin(),
        ]);
    }

    /** The signed-in agency; null for Super Admin (every agency). Agent logins have no access. */
    private function agency(): ?PortalUser
    {
        if ($this->isAdmin()) {
            return null;
        }
        $owner = $this->owner();
        abort_unless($owner && $owner->type === 'company', 403, 'Agent insights are for agency accounts.');

        return $owner;
    }

    private function agentJson(PortalUser $agent): array
    {
        return [
            'id' => $agent->id,
            'name' => $agent->name,
            'email' => $agent->email,
            'avatar' => $agent->avatar ? media_url($agent->avatar) : null,
            'agency' => $this->isAdmin() ? $agent->company?->displayName() : null,
            'suspended' => $agent->currentMembership?->status === AgencyAgent::SUSPENDED,
        ];
    }

    private function days(Request $request): int
    {
        $days = $request->integer('days', 30);

        return array_key_exists($days, AgentInsightsService::PERIODS) ? $days : 30;
    }

    private function options(array $options): array
    {
        return collect($options)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all();
    }

    private function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
        ];
    }
}
