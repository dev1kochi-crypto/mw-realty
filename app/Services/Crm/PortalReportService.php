<?php

namespace App\Services\Crm;

use App\Models\AgencyAgent;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\PlanPayment;
use App\Models\PortalUser;
use App\Models\Property;
use App\Services\PropertyLabels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Portal Reports (Leads / Properties / Sales / Agents). Every query is scoped the same way the CRM and
 * Properties screens already are — Lead::forOwner / Property::accessibleBy — so a report can
 * never show more than the account can open elsewhere. A null viewer is Super Admin (all data).
 */
class PortalReportService
{
    public const RANGES = [7 => '7 Days', 30 => '30 Days', 90 => '90 Days', 365 => '12 Months'];

    public function __construct(private readonly PropertyLabels $labels)
    {
    }

    public function leads(?PortalUser $viewer, int $days): array
    {
        $since = $this->since($days);
        $leads = fn () => Lead::forOwner($viewer?->id);

        $total = $leads()->count();
        $inRange = $leads()->where('leads.created_at', '>=', $since)->count();
        $closed = $leads()->whereHas('stage', fn ($s) => $s->where('is_closed', true))->count();
        $previous = $leads()->whereBetween('leads.created_at', [$since->copy()->subDays($days), $since])->count();

        return [
            'kpis' => [
                'total' => $total,
                'in_range' => $inRange,
                'change' => $previous > 0 ? round(($inRange - $previous) / $previous * 100) : null,
                'closed' => $closed,
                'conversion' => $total > 0 ? round($closed / $total * 100, 1) : 0.0,
                'unassigned' => $viewer?->type === 'company' ? $leads()->where('leads.portal_user_id', $viewer->id)->whereNull('leads.agent_id')->count() : null,
            ],
            'overTime' => $this->perDay($leads()->where('leads.created_at', '>=', $since), 'leads.created_at', $days),
            'byStage' => $leads()->join('lead_stages', 'lead_stages.id', '=', 'leads.stage_id')
                ->selectRaw('lead_stages.name, lead_stages.color, count(*) as total')
                ->groupBy('lead_stages.id', 'lead_stages.name', 'lead_stages.color', 'lead_stages.order_index')
                ->orderBy('lead_stages.order_index')->get(),
            // A CRM source when one was picked, otherwise where it came in from (property page, chatbot…).
            'bySource' => $leads()->leftJoin('lead_sources', 'lead_sources.id', '=', 'leads.source_id')
                ->selectRaw("COALESCE(lead_sources.name, NULLIF(leads.page_source, ''), 'Direct / Manual') as label, count(*) as total")
                ->groupBy('label')->orderByDesc('total')->limit(8)->pluck('total', 'label'),
            'byStatus' => $leads()->selectRaw('leads.status, count(*) as total')->groupBy('leads.status')->pluck('total', 'status'),
            'topProperties' => $leads()->whereNotNull('leads.property_id')
                ->where('leads.created_at', '>=', $since)
                ->selectRaw('leads.property_id, count(*) as total')
                ->groupBy('leads.property_id')->orderByDesc('total')->limit(5)
                ->with('property:id,translations,reference_no,listing_type,price,currency')
                ->get(),
        ];
    }

    public function properties(?PortalUser $viewer, int $days): array
    {
        $since = $this->since($days);
        $properties = fn () => Property::accessibleBy($viewer);
        $labels = $this->labels->values();
        $label = fn (string $key, ?string $value) => $value
            ? (($labels[$key][$value] ?? null)?->getTranslation('label') ?? ucwords(str_replace('-', ' ', $value)))
            : 'Not set';

        $now = now();

        return [
            'kpis' => [
                'total' => $properties()->count(),
                'active' => $properties()->where('status', true)->count(),
                'inactive' => $properties()->where('status', false)->count(),
                'featured' => $properties()->where('featured', true)
                    ->where(fn ($q) => $q->whereNull('featured_from')->orWhere('featured_from', '<=', $now))
                    ->where(fn ($q) => $q->whereNull('featured_until')->orWhere('featured_until', '>=', $now))->count(),
                'new' => $properties()->where('created_at', '>=', $since)->count(),
            ],
            'overTime' => $this->perDay($properties()->where('created_at', '>=', $since), 'properties.created_at', $days),
            'byListingType' => $properties()->selectRaw('listing_type, count(*) as total')->groupBy('listing_type')->pluck('total', 'listing_type')
                ->mapWithKeys(fn ($total, $type) => [$label('listing_type', $type) => $total]),
            'bySegment' => $properties()->selectRaw('segment, count(*) as total')->groupBy('segment')->pluck('total', 'segment')
                ->mapWithKeys(fn ($total, $segment) => [ucfirst($segment ?: 'residential') => $total]),
            'byType' => $properties()->selectRaw('property_type, count(*) as total')->groupBy('property_type')->orderByDesc('total')->limit(8)
                ->pluck('total', 'property_type')->mapWithKeys(fn ($total, $type) => [$label('property_type', $type) => $total]),
            'topByLeads' => $properties()
                ->withCount(['leads as leads_total', 'leads as leads_in_range' => fn ($q) => $q->where('created_at', '>=', $since)])
                ->orderByDesc('leads_in_range')->orderByDesc('leads_total')
                ->limit(8)->get(['id', 'translations', 'reference_no', 'listing_type', 'price', 'currency', 'status', 'agent_id'])
                ->filter(fn ($p) => $p->leads_total > 0)->values(),
        ];
    }

    /** Agency (or Super Admin) only — one row per current member agent. */
    public function agents(?PortalUser $viewer, int $days): array
    {
        $since = $this->since($days);

        $memberships = AgencyAgent::query()
            ->when($viewer, fn ($q) => $q->where('agency_id', $viewer->id))
            ->whereIn('status', [...AgencyAgent::MEMBER_STATUSES, AgencyAgent::PENDING])
            ->with(['agent:id,name,email,phone,company_id', 'agency:id,name,company_name,type'])
            ->get();

        $agentIds = $memberships->pluck('agent_id')->all();
        $agencyIds = $memberships->pluck('agency_id')->unique()->all();

        // Agency-owned work assigned to each agent, counted in one grouped query per metric.
        $propertyStats = Property::whereIn('agent_id', $agentIds)->whereIn('portal_user_id', $agencyIds)
            ->selectRaw('agent_id, count(*) as total, SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active')
            ->groupBy('agent_id')->get()->keyBy('agent_id');

        $leadStats = Lead::whereIn('leads.agent_id', $agentIds)->whereIn('leads.portal_user_id', $agencyIds)
            ->leftJoin('lead_stages', 'lead_stages.id', '=', 'leads.stage_id')
            ->selectRaw('leads.agent_id, count(*) as total,
                SUM(CASE WHEN leads.created_at >= ? THEN 1 ELSE 0 END) as in_range,
                SUM(CASE WHEN lead_stages.is_closed = 1 THEN 1 ELSE 0 END) as closed,
                MAX(leads.created_at) as last_lead_at', [$since])
            ->groupBy('leads.agent_id')->get()->keyBy('agent_id');

        $rows = $memberships->map(function (AgencyAgent $membership) use ($propertyStats, $leadStats) {
            $p = $propertyStats[$membership->agent_id] ?? null;
            $l = $leadStats[$membership->agent_id] ?? null;
            $leadsTotal = (int) ($l->total ?? 0);
            $closed = (int) ($l->closed ?? 0);

            return (object) [
                'membership' => $membership,
                'agent' => $membership->agent,
                'agency' => $membership->agency,
                'properties' => (int) ($p->total ?? 0),
                'active_properties' => (int) ($p->active ?? 0),
                'leads' => $leadsTotal,
                'leads_in_range' => (int) ($l->in_range ?? 0),
                'closed' => $closed,
                'conversion' => $leadsTotal > 0 ? round($closed / $leadsTotal * 100, 1) : 0.0,
                'last_lead_at' => ($l->last_lead_at ?? null) ? Carbon::parse($l->last_lead_at) : null,
            ];
        })->filter(fn ($row) => $row->agent)->sortByDesc(fn ($row) => [$row->leads_in_range, $row->leads])->values();

        $members = $rows->filter(fn ($r) => in_array($r->membership->status, AgencyAgent::MEMBER_STATUSES, true));

        return [
            'kpis' => [
                'active' => $rows->where('membership.status', AgencyAgent::APPROVED)->count(),
                'suspended' => $rows->where('membership.status', AgencyAgent::SUSPENDED)->count(),
                'pending' => $rows->where('membership.status', AgencyAgent::PENDING)->count(),
                'properties' => $members->sum('properties'),
                'leads_in_range' => $members->sum('leads_in_range'),
                'unassigned_properties' => $viewer ? Property::where('portal_user_id', $viewer->id)->whereNull('agent_id')->count() : null,
            ],
            'rows' => $rows,
            'chart' => $members->take(10)->mapWithKeys(fn ($r) => [$r->agent->name => $r->leads_in_range]),
        ];
    }

    private function since(int $days): Carbon
    {
        return now()->subDays($days - 1)->startOfDay();
    }

    public const SALE_OUTCOMES = ['won' => 'Won', 'lost' => 'Lost', 'all' => 'Won & Lost'];

    /** A deal's value: the recorded sold / rent price when this lead is the listing's buyer, else the listed price. */
    public const DEAL_VALUE = 'CASE WHEN properties.sold_lead_id = leads.id AND properties.sold_price IS NOT NULL THEN properties.sold_price ELSE properties.price END';

    /**
     * Sales for everyone (a won lead is worth its property's price, AED), plus MW Realty's own plan
     * sales for Super Admin. Won/lost = closed stage, split by LeadStage::nameIsLost(). `deals` is the
     * full, paginated list of every closed deal in the range (filters: q, outcome, listing).
     */
    public function sales(?PortalUser $viewer, int $days, array $filters = []): array
    {
        $since = $this->since($days);
        $previousSince = $since->copy()->subDays($days);

        [$closedIds, $wonIds, $lostIds] = $this->closedStageIds();

        $deals = fn () => Lead::forOwner($viewer?->id)->join('properties', 'properties.id', '=', 'leads.property_id');
        $won = fn () => $deals()->whereIn('leads.stage_id', $wonIds);
        $wonInRange = fn () => $won()->where('leads.closed_at', '>=', $since);
        $lostInRange = fn () => $deals()->whereIn('leads.stage_id', $lostIds)->where('leads.closed_at', '>=', $since);
        $open = fn () => $deals()->where(fn ($q) => $q->whereNull('leads.stage_id')->orWhereNotIn('leads.stage_id', $closedIds));

        // One aggregate query per bucket instead of a sum() + count() pair each.
        $totals = fn ($query) => $query->selectRaw('count(*) as deals, COALESCE(SUM(' . self::DEAL_VALUE . '), 0) as total')->first();
        $wonNow = $totals($wonInRange());
        $wonBefore = $totals($won()->whereBetween('leads.closed_at', [$previousSince, $since]));
        $lostNow = $totals($lostInRange());
        $openNow = $totals($open());
        $allTime = $totals($won());

        $wonValue = (float) $wonNow->total;
        $wonCount = (int) $wonNow->deals;
        $previousWon = (float) $wonBefore->total;
        $lostCount = (int) $lostNow->deals;

        $labels = $this->labels->values();
        $label = fn (string $key, ?string $value) => $value
            ? (($labels[$key][$value] ?? null)?->getTranslation('label') ?? ucwords(str_replace('-', ' ', $value)))
            : 'Not set';
        $groupBy = fn (string $column, string $key) => $wonInRange()
            ->selectRaw("properties.{$column} as grp, SUM(" . self::DEAL_VALUE . ") as total, count(*) as deals")
            ->groupBy("properties.{$column}")->orderByDesc('total')->limit(8)->get()
            ->map(fn ($row) => (object) ['name' => $label($key, $row->grp), 'total' => (float) $row->total, 'deals' => (int) $row->deals]);

        $data = [
            'kpis' => [
                'won_value' => $wonValue,
                'change' => $previousWon > 0 ? round(($wonValue - $previousWon) / $previousWon * 100) : null,
                'won_count' => $wonCount,
                'average' => $wonCount > 0 ? $wonValue / $wonCount : 0.0,
                'lost_value' => (float) $lostNow->total,
                'lost_count' => $lostCount,
                'win_rate' => ($wonCount + $lostCount) > 0 ? round($wonCount / ($wonCount + $lostCount) * 100, 1) : null,
                // Open pipeline: every not-yet-closed lead on a listing, at that listing's price.
                'pipeline_value' => (float) $openNow->total,
                'pipeline_count' => (int) $openNow->deals,
                'all_time_value' => (float) $allTime->total,
                'all_time_count' => (int) $allTime->deals,
            ],
            'overTime' => $this->perDay($wonInRange(), 'leads.closed_at', $days, 'SUM(' . self::DEAL_VALUE . ')'),
            'byListingType' => $wonInRange()
                ->selectRaw('properties.listing_type, SUM(' . self::DEAL_VALUE . ') as total')->groupBy('properties.listing_type')
                ->pluck('total', 'listing_type')->mapWithKeys(fn ($total, $type) => [ucfirst($type ?: 'Other') => (float) $total]),
            'byPropertyType' => $groupBy('property_type', 'property_type'),
            'byLocation' => $groupBy('location', 'location'),
            'byAgent' => $viewer?->type === 'company' || !$viewer
                ? $wonInRange()->whereNotNull('leads.agent_id')
                    ->join('portal_users as agents', 'agents.id', '=', 'leads.agent_id')
                    ->selectRaw('agents.name, SUM(' . self::DEAL_VALUE . ') as total, count(*) as deals')
                    ->groupBy('agents.id', 'agents.name')->orderByDesc('total')->limit(10)->get()
                : collect(),
            // Super Admin: sales per account (agency / independent agent).
            'byAccount' => !$viewer
                ? $wonInRange()->join('portal_users as owners', 'owners.id', '=', 'leads.portal_user_id')
                    ->selectRaw('owners.name, owners.company_name, owners.type, SUM(' . self::DEAL_VALUE . ') as total, count(*) as deals')
                    ->groupBy('owners.id', 'owners.name', 'owners.company_name', 'owners.type')->orderByDesc('total')->limit(10)->get()
                : collect(),
            'deals' => $this->salesDeals($viewer, $days, $filters)->paginate(20)->withQueryString(),
            'plans' => null,
        ];

        if (!$viewer) {
            $payments = fn () => PlanPayment::paid();
            $paidTotal = (float) $payments()->where('paid_at', '>=', $since)->sum('amount');
            $paidPrevious = (float) $payments()->whereBetween('paid_at', [$previousSince, $since])->sum('amount');

            $data['plans'] = [
                'total' => $paidTotal,
                'change' => $paidPrevious > 0 ? round(($paidTotal - $paidPrevious) / $paidPrevious * 100) : null,
                'count' => $payments()->where('paid_at', '>=', $since)->count(),
                'discounts' => (float) $payments()->where('paid_at', '>=', $since)->sum('discount_amount'),
                'paying_accounts' => $payments()->where('paid_at', '>=', $since)->distinct()->count('portal_user_id'),
                'overTime' => $this->perDay($payments()->where('paid_at', '>=', $since), 'plan_payments.paid_at', $days, 'SUM(amount)'),
                'byPlan' => $payments()->where('paid_at', '>=', $since)->selectRaw('plan_name, SUM(amount) as total, count(*) as payments')
                    ->groupBy('plan_name')->orderByDesc('total')->get(),
            ];
        }

        return $data;
    }

    /** Every closed deal in the range, newest first — the Sales list and its CSV export share this. */
    public function salesDeals(?PortalUser $viewer, int $days, array $filters = [])
    {
        [, $wonIds, $lostIds] = $this->closedStageIds();
        $outcome = array_key_exists($filters['outcome'] ?? '', self::SALE_OUTCOMES) ? $filters['outcome'] : 'won';
        $search = trim((string) ($filters['q'] ?? ''));
        $listing = in_array($filters['listing'] ?? '', ['sale', 'rent'], true) ? $filters['listing'] : null;

        return Lead::forOwner($viewer?->id)
            ->join('properties', 'properties.id', '=', 'leads.property_id')
            ->whereIn('leads.stage_id', match ($outcome) {
                'won' => $wonIds,
                'lost' => $lostIds,
                default => array_merge($wonIds, $lostIds),
            })
            ->where('leads.closed_at', '>=', $this->since($days))
            ->when($listing, fn ($q) => $q->where('properties.listing_type', $listing))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where(fn ($w) => $w->where('leads.name', 'like', $like)
                    ->orWhere('leads.email', 'like', $like)
                    ->orWhere('leads.phone', 'like', $like)
                    ->orWhere('properties.reference_no', 'like', $like)
                    ->orWhere('properties.translations', 'like', $like)
                    ->orWhereIn('leads.agent_id', PortalUser::where('name', 'like', $like)->select('id')));
            })
            ->with(['agent:id,name', 'stage:id,name,color', 'owner:id,name,company_name,type'])
            ->select('leads.id', 'leads.name', 'leads.email', 'leads.phone', 'leads.phone_country_code', 'leads.portal_user_id',
                'leads.agent_id', 'leads.stage_id', 'leads.created_at', 'leads.closed_at', 'properties.id as property_id',
                'properties.translations as property_translations', 'properties.reference_no', 'properties.listing_type',
                'properties.property_type', 'properties.location', 'properties.price')
            ->selectRaw(self::DEAL_VALUE . ' as deal_value')
            ->orderByDesc('leads.closed_at')->orderByDesc('leads.id');
    }

    /** [closed, won, lost] stage ids — never empty, so whereIn() / whereNotIn() stay valid. */
    private function closedStageIds(): array
    {
        return $this->closedStageIds ??= (function () {
            $closedStages = LeadStage::where('is_closed', true)->get(['id', 'name']);
            [$lostStages, $wonStages] = $closedStages->partition(fn ($s) => LeadStage::nameIsLost($s->name));

            return [
                $closedStages->pluck('id')->all() ?: [0],
                $wonStages->pluck('id')->all() ?: [0],
                $lostStages->pluck('id')->all() ?: [0],
            ];
        })();
    }

    private ?array $closedStageIds = null;

    /** Per-day totals with zero-filled gaps (monthly buckets for the 12-month range) — counts by default, or any aggregate. */
    private function perDay($query, string $column, int $days, string $aggregate = 'count(*)'): Collection
    {
        $monthly = $days > 90;
        $format = $monthly ? '%Y-%m' : '%Y-%m-%d';
        $expression = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('{$format}', {$column})"
            : "DATE_FORMAT({$column}, '{$format}')";

        $totals = $query->selectRaw("{$expression} as bucket, {$aggregate} as total")->groupBy('bucket')->pluck('total', 'bucket');

        $series = collect();
        $cursor = $this->since($days);
        while ($cursor->lte(now())) {
            $key = $cursor->format($monthly ? 'Y-m' : 'Y-m-d');
            $series[$monthly ? $cursor->format('M Y') : $cursor->format('d M')] = (float) ($totals[$key] ?? 0);
            $monthly ? $cursor->addMonthNoOverflow()->startOfMonth() : $cursor->addDay();
        }

        return $series;
    }
}
