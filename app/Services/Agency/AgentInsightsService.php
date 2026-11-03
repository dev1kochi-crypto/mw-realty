<?php

namespace App\Services\Agency;

use App\Models\AgencyAgent;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\PortalUser;
use App\Models\Property;
use App\Services\ListingQualityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agent Insights (CRM › Agent Insights): how well each agent of an agency handles the leads and
 * listings the agency gave them — response rate and speed, leads left untouched, overdue
 * follow-ups, listing quality and how many of their listings are live — rolled up into an Agent
 * score out of 100 with a tip for every check that isn't full marks.
 *
 * Only the agency's own records count (leads / listings with portal_user_id = the agent's
 * company_id and agent_id = the agent), the same scope as the Agents roster numbers.
 *
 * "Acting on a lead" = a team note, a stage or status change, or a follow-up (scheduled or logged)
 * after the lead reached the agent. System entries (repeat enquiries, assignment) don't count.
 */
class AgentInsightsService
{
    public const PERIODS = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'];

    public const SORTS = ['attention' => 'Needs attention first', 'name' => 'Name'];

    /** Activity-log types that mean someone worked the lead (see LeadNoteService). */
    public const ACTION_NOTE_TYPES = ['note', 'stage', 'status'];

    /** A lead counts as answered when acted on within this many hours, and as untouched after it. */
    public const RESPONSE_HOURS = 24;

    /** When the lead reached its current agent (leads the agent added themselves have no assigned_at). */
    private const RECEIVED_AT = 'COALESCE(leads.assigned_at, leads.created_at)';

    public function __construct(private readonly ListingQualityService $quality)
    {
    }

    /**
     * Agents shown on the screen: current members (active or suspended) of the agency — or, for
     * Super Admin ($agency null), of every agency.
     */
    public function agentsQuery(?PortalUser $agency): Builder
    {
        return PortalUser::query()
            ->where('portal_users.type', 'agent')
            ->whereNotNull('portal_users.company_id')
            ->when($agency, fn ($q) => $q->where('portal_users.company_id', $agency->id))
            ->whereExists(fn ($q) => $q->from('agency_agents')
                ->whereColumn('agency_agents.agent_id', 'portal_users.id')
                ->whereColumn('agency_agents.agency_id', 'portal_users.company_id')
                ->whereIn('agency_agents.status', AgencyAgent::MEMBER_STATUSES));
    }

    /** "Needs attention first": most untouched leads + overdue follow-ups on top, then by name. */
    public function orderByAttention(Builder $agents): Builder
    {
        $ofAgent = fn (Builder $q) => $q->selectRaw('count(*)')
            ->whereColumn('leads.agent_id', 'portal_users.id')
            ->whereColumn('leads.portal_user_id', 'portal_users.company_id')
            ->toBase();
        $untouched = $ofAgent($this->untouchedLeads(Lead::query()));
        $overdue = $ofAgent($this->overdueFollowUps());

        return $agents->select('portal_users.*')
            ->selectRaw("(({$untouched->toSql()}) + ({$overdue->toSql()})) as attention", [...$untouched->getBindings(), ...$overdue->getBindings()])
            ->orderByDesc('attention');
    }

    /**
     * The insight numbers + score for each agent, keyed by agent id.
     *
     * @param  Collection<int, PortalUser>  $agents
     * @return array<int, array>
     */
    public function forAgents(Collection $agents, int $days): array
    {
        $ids = $agents->pluck('id')->all();
        if (!$ids) {
            return [];
        }
        $since = now()->subDays($days);

        $responses = $this->responses($ids, $since);
        $open = $this->countByAgent($this->agencyLeads($ids)->whereNull('leads.closed_at'));
        $untouched = $this->countByAgent($this->untouchedLeads($this->agencyLeads($ids)));
        $scheduled = $this->countByAgent($this->agencyFollowUps($ids)->where('lead_follow_ups.status', LeadFollowUp::SCHEDULED));
        $overdue = $this->countByAgent($this->agencyFollowUps($ids)->where('lead_follow_ups.status', LeadFollowUp::SCHEDULED)->where('lead_follow_ups.due_at', '<', now()));
        $closed = $this->countByAgent($this->agencyLeads($ids)->where('leads.closed_at', '>=', $since));
        $deals = Property::query()
            ->join('portal_users as agent', fn ($j) => $j->on('agent.id', '=', 'properties.sold_agent_id')->on('agent.company_id', '=', 'properties.portal_user_id'))
            ->whereIn('properties.sold_agent_id', $ids)
            ->where('properties.sold_at', '>=', $since)
            ->selectRaw('properties.sold_agent_id as agent_id, count(*) as total')
            ->groupBy('properties.sold_agent_id')
            ->pluck('total', 'agent_id');
        $listings = $this->listings($ids);

        return $agents->mapWithKeys(function (PortalUser $agent) use ($responses, $open, $untouched, $scheduled, $overdue, $closed, $deals, $listings) {
            $r = $responses[$agent->id] ?? ['received' => 0, 'counted' => 0, 'answered' => 0, 'median_minutes' => null];
            $l = $listings[$agent->id] ?? ['total' => 0, 'live' => 0, 'issues' => 0, 'low_quality' => 0, 'avg_quality' => null];
            $metrics = [
                'leads' => [
                    'received' => $r['received'],
                    'counted' => $r['counted'], // received leads old enough to judge (or already answered)
                    'answered' => $r['answered'],
                    'response_rate' => $r['counted'] ? (int) round(100 * $r['answered'] / $r['counted']) : null,
                    'median_minutes' => $r['median_minutes'],
                    'open' => (int) ($open[$agent->id] ?? 0),
                    'untouched' => (int) ($untouched[$agent->id] ?? 0),
                    'closed' => (int) ($closed[$agent->id] ?? 0),
                ],
                'follow_ups' => [
                    'scheduled' => (int) ($scheduled[$agent->id] ?? 0),
                    'overdue' => (int) ($overdue[$agent->id] ?? 0),
                ],
                'listings' => $l,
                'deals' => (int) ($deals[$agent->id] ?? 0),
            ];

            return [$agent->id => $metrics + ['score' => $this->score($metrics)]];
        })->all();
    }

    /** Team cards — plain counts across every agent the viewer can see (cheap at any size). */
    public function totals(?PortalUser $agency, int $days): array
    {
        $agentIds = $this->agentsQuery($agency)->select('portal_users.id');
        $leads = fn () => Lead::query()
            ->whereIn('leads.agent_id', $agentIds)
            ->when($agency, fn ($q) => $q->where('leads.portal_user_id', $agency->id));

        return [
            'agents' => $this->agentsQuery($agency)->count(),
            'received' => $leads()->whereRaw(self::RECEIVED_AT . ' >= ?', [now()->subDays($days)])->count(),
            'untouched' => $this->untouchedLeads($leads())->count(),
            'overdue_follow_ups' => $this->overdueFollowUps()->whereIn('leads.agent_id', $agentIds)
                ->when($agency, fn ($q) => $q->where('leads.portal_user_id', $agency->id))->count(),
            'live_listings' => Property::query()->whereIn('properties.agent_id', $agentIds)
                ->when($agency, fn ($q) => $q->where('properties.portal_user_id', $agency->id))
                ->available()->active()->compliant()->count(),
        ];
    }

    /** One agent's open leads nobody has acted on since they got them (oldest first). */
    public function untouchedLeadsOf(PortalUser $agent): Builder
    {
        return $this->untouchedLeads($this->agencyLeads([$agent->id]))
            ->select('leads.*')
            ->selectRaw(self::RECEIVED_AT . ' as received_at')
            ->with('stage')
            ->orderBy('received_at');
    }

    /** One agent's follow-ups that are past due (most overdue first). */
    public function overdueFollowUpsOf(PortalUser $agent): Builder
    {
        return $this->agencyFollowUps([$agent->id])
            ->where('lead_follow_ups.status', LeadFollowUp::SCHEDULED)
            ->where('lead_follow_ups.due_at', '<', now())
            ->select('lead_follow_ups.*', 'leads.name as lead_name')
            ->orderBy('lead_follow_ups.due_at');
    }

    /**
     * One agent's listings that need work: not live, permit problem or quality below 50 —
     * worst quality first. Quality is calculated (ListingQualityService), so this runs in PHP.
     */
    public function listingsNeedingWorkOf(PortalUser $agent): Collection
    {
        return $this->agencyListings([$agent->id])->get()
            ->map(function (Property $p) {
                $quality = $this->quality->score($p);
                $issues = array_values(array_filter([
                    $p->compliance_status === Property::COMPLIANCE_CHANGES_REQUESTED ? 'Changes requested' : null,
                    $p->compliance_status === Property::COMPLIANCE_EXPIRED || ($p->permit_expires_at && $p->permit_expires_at->lt(today())) ? 'Permit expired' : null,
                    in_array($p->compliance_status, [Property::COMPLIANCE_DRAFT, Property::COMPLIANCE_PENDING], true) ? 'Permit not approved yet' : null,
                    $p->review_hold ? 'On hold for review' : null,
                    !$p->status ? 'Inactive' : null,
                    $quality['score'] < 50 ? 'Low quality' : null,
                ]));

                return $issues ? ['property' => $p, 'quality' => $quality, 'issues' => $issues] : null;
            })
            ->filter()
            ->sortBy(fn ($row) => $row['quality']['score'])
            ->values();
    }

    /** The agency's leads assigned to these agents (each agent's own company only). */
    private function agencyLeads(array $agentIds): Builder
    {
        return Lead::query()
            ->join('portal_users as agent', fn ($j) => $j->on('agent.id', '=', 'leads.agent_id')->on('agent.company_id', '=', 'leads.portal_user_id'))
            ->whereIn('leads.agent_id', $agentIds);
    }

    private function agencyFollowUps(array $agentIds): Builder
    {
        return LeadFollowUp::query()
            ->join('leads', 'leads.id', '=', 'lead_follow_ups.lead_id')
            ->join('portal_users as agent', fn ($j) => $j->on('agent.id', '=', 'leads.agent_id')->on('agent.company_id', '=', 'leads.portal_user_id'))
            ->whereNull('leads.deleted_at')
            ->whereIn('leads.agent_id', $agentIds);
    }

    private function agencyListings(array $agentIds): Builder
    {
        return Property::query()
            ->select('properties.*')
            ->join('portal_users as agent', fn ($j) => $j->on('agent.id', '=', 'properties.agent_id')->on('agent.company_id', '=', 'properties.portal_user_id'))
            ->whereIn('properties.agent_id', $agentIds)
            ->available()
            ->with(['details', 'floorPlans']);
    }

    /** Open leads received more than RESPONSE_HOURS ago with no action since. */
    private function untouchedLeads(Builder $leads): Builder
    {
        return $leads->whereNull('leads.closed_at')
            ->whereNotNull('leads.agent_id')
            ->whereRaw(self::RECEIVED_AT . ' < ?', [now()->subHours(self::RESPONSE_HOURS)])
            ->whereNotExists(fn ($q) => $q->from('lead_notes')->whereColumn('lead_notes.lead_id', 'leads.id')
                ->whereIn('lead_notes.type', self::ACTION_NOTE_TYPES)
                ->whereRaw('lead_notes.created_at >= ' . self::RECEIVED_AT))
            ->whereNotExists(fn ($q) => $q->from('lead_follow_ups')->whereColumn('lead_follow_ups.lead_id', 'leads.id')
                ->whereRaw('lead_follow_ups.created_at >= ' . self::RECEIVED_AT));
    }

    private function overdueFollowUps(): Builder
    {
        return LeadFollowUp::query()
            ->join('leads', 'leads.id', '=', 'lead_follow_ups.lead_id')
            ->whereNull('leads.deleted_at')
            ->where('lead_follow_ups.status', LeadFollowUp::SCHEDULED)
            ->where('lead_follow_ups.due_at', '<', now());
    }

    private function countByAgent(Builder $query): Collection
    {
        return $query->selectRaw('leads.agent_id, count(*) as total')->groupBy('leads.agent_id')->pluck('total', 'agent_id');
    }

    /**
     * Leads received in the period: how many were acted on within RESPONSE_HOURS and the median
     * time to the first action. Leads younger than RESPONSE_HOURS with no action yet aren't judged.
     */
    private function responses(array $agentIds, $since): array
    {
        $firstAfter = fn (string $table, ?array $types = null) => DB::table($table)
            ->selectRaw("min({$table}.created_at)")
            ->whereColumn("{$table}.lead_id", 'leads.id')
            ->when($types, fn ($q) => $q->whereIn("{$table}.type", $types))
            ->whereRaw("{$table}.created_at >= " . self::RECEIVED_AT);

        $rows = $this->agencyLeads($agentIds)
            ->whereRaw(self::RECEIVED_AT . ' >= ?', [$since])
            ->selectRaw('leads.agent_id, ' . self::RECEIVED_AT . ' as received_at')
            ->selectSub($firstAfter('lead_notes', self::ACTION_NOTE_TYPES), 'first_note_at')
            ->selectSub($firstAfter('lead_follow_ups'), 'first_follow_up_at')
            ->toBase()
            ->get();

        $judgeBefore = now()->subHours(self::RESPONSE_HOURS);

        return $rows->groupBy('agent_id')->map(function (Collection $leads) use ($judgeBefore) {
            $minutes = [];
            $counted = 0;
            $answered = 0;
            foreach ($leads as $lead) {
                $received = \Illuminate\Support\Carbon::parse($lead->received_at);
                $first = collect([$lead->first_note_at, $lead->first_follow_up_at])->filter()->map(fn ($t) => \Illuminate\Support\Carbon::parse($t))->min();
                if ($first) {
                    $minutes[] = max(0, (int) round($received->diffInMinutes($first, true)));
                }
                if ($first || $received->lt($judgeBefore)) {
                    $counted++;
                    $answered += $first && $received->diffInHours($first, true) <= self::RESPONSE_HOURS ? 1 : 0;
                }
            }
            sort($minutes);
            $n = count($minutes);

            return [
                'received' => $leads->count(),
                'counted' => $counted,
                'answered' => $answered,
                'median_minutes' => $n ? ($n % 2 ? $minutes[intdiv($n, 2)] : (int) round(($minutes[$n / 2 - 1] + $minutes[$n / 2]) / 2)) : null,
            ];
        })->all();
    }

    /** Listing counts and average quality per agent (their agency listings still on the market). */
    private function listings(array $agentIds): array
    {
        $result = [];
        $this->agencyListings($agentIds)->chunkById(200, function ($properties) use (&$result) {
            foreach ($properties as $p) {
                $row = &$result[$p->agent_id];
                $row ??= ['total' => 0, 'live' => 0, 'issues' => 0, 'low_quality' => 0, 'quality_sum' => 0];
                $score = $this->quality->score($p)['score'];
                $row['total']++;
                $row['quality_sum'] += $score;
                $row['live'] += $p->status && $p->canGoLive() ? 1 : 0;
                $row['issues'] += in_array($p->compliance_status, [Property::COMPLIANCE_CHANGES_REQUESTED, Property::COMPLIANCE_EXPIRED], true) ? 1 : 0;
                $row['low_quality'] += $score < 50 ? 1 : 0;
                unset($row);
            }
        }, 'properties.id', 'id');

        return array_map(fn ($row) => [
            'total' => $row['total'],
            'live' => $row['live'],
            'issues' => $row['issues'],
            'low_quality' => $row['low_quality'],
            'avg_quality' => (int) round($row['quality_sum'] / $row['total']),
        ], $result);
    }

    /**
     * Agent score out of 100 — the points of every check that applies, scaled to 100 (a check
     * with nothing to judge, e.g. no leads received, is left out rather than scored 0).
     * Same shape as ListingQualityService::score() so the CRM's breakdown styling fits.
     */
    private function score(array $m): array
    {
        $leads = $m['leads'];
        $listings = $m['listings'];
        $rate = $leads['response_rate'];
        $median = $leads['median_minutes'];

        $groups = [
            [
                $this->check('Response rate', $rate === null ? null : (int) round(25 * $rate / 100), 25,
                    "Act on every new lead within " . self::RESPONSE_HOURS . " hours: add a note, change the stage or log a follow-up ({$rate}% now)."),
                $this->check('Response time', $median === null ? null : match (true) {
                    $median <= 15 => 15,
                    $median <= 60 => 12,
                    $median <= 240 => 8,
                    $median <= 1440 => 4,
                    default => 0,
                }, 15, 'Reach new leads faster — the first action within 15 minutes earns full points.'),
                $this->check('No untouched leads', $leads['open'] ? (int) round(10 * (1 - $leads['untouched'] / $leads['open'])) : null, 10,
                    "{$leads['untouched']} open " . ($leads['untouched'] === 1 ? 'lead has' : 'leads have') . ' had no activity since they were assigned.'),
                $this->check('Follow-ups on time', $m['follow_ups']['scheduled'] ? (int) round(15 * (1 - $m['follow_ups']['overdue'] / $m['follow_ups']['scheduled'])) : null, 15,
                    "Complete or reschedule {$m['follow_ups']['overdue']} overdue follow-up" . ($m['follow_ups']['overdue'] === 1 ? '' : 's') . '.'),
            ],
            [
                $this->check('Listing quality', $listings['total'] ? (int) round(20 * $listings['avg_quality'] / 100) : null, 20,
                    "Improve listings: more photos, a fuller description, map pin and a verified permit (average {$listings['avg_quality']}/100)."),
                $this->check('Live listings', $listings['total'] ? (int) round(15 * $listings['live'] / $listings['total']) : null, 15,
                    ($listings['total'] - $listings['live']) . " of {$listings['total']} listings are not live — check their status and permits."),
            ],
        ];

        $applicable = collect($groups)->flatten(1)->where('applies', true);
        if ($applicable->isEmpty()) {
            return ['score' => null, 'max' => 100, 'tone' => null, 'groups' => $groups];
        }
        $score = (int) round(100 * $applicable->sum('points') / $applicable->sum('max'));

        return [
            'score' => $score,
            'max' => 100,
            'tone' => $score >= 80 ? 'good' : ($score >= 50 ? 'fair' : 'poor'),
            'groups' => $groups,
        ];
    }

    private function check(string $label, ?int $points, int $max, string $tip): array
    {
        if ($points === null) {
            return ['label' => $label, 'points' => 0, 'max' => $max, 'applies' => false, 'tip' => null];
        }
        $points = max(0, min($points, $max));

        return ['label' => $label, 'points' => $points, 'max' => $max, 'applies' => true, 'tip' => $points >= $max ? null : $tip];
    }
}
