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
 * Portal Reports (Leads / Properties / Agents). Every query is scoped the same way the CRM and
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

    /**
     * Deal revenue for everyone (a won lead is worth its property's price, AED), plus MW Realty's
     * own plan revenue for Super Admin. Won/lost = closed stage, split by LeadStage::nameIsLost().
     */
    public function revenue(?PortalUser $viewer, int $days): array
    {
        $since = $this->since($days);
        $previousSince = $since->copy()->subDays($days);

        $closedStages = LeadStage::where('is_closed', true)->get(['id', 'name']);
        [$lostStages, $wonStages] = $closedStages->partition(fn ($s) => LeadStage::nameIsLost($s->name));
        $wonIds = $wonStages->pluck('id')->all() ?: [0];
        $lostIds = $lostStages->pluck('id')->all() ?: [0];

        $deals = fn () => Lead::forOwner($viewer?->id)->join('properties', 'properties.id', '=', 'leads.property_id');
        $won = fn () => $deals()->whereIn('leads.stage_id', $wonIds);

        $wonValue = (float) $won()->where('leads.closed_at', '>=', $since)->sum('properties.price');
        $wonCount = $won()->where('leads.closed_at', '>=', $since)->count();
        $previousWon = (float) $won()->whereBetween('leads.closed_at', [$previousSince, $since])->sum('properties.price');
        $lostCount = $deals()->whereIn('leads.stage_id', $lostIds)->where('leads.closed_at', '>=', $since)->count();

        $data = [
            'kpis' => [
                'won_value' => $wonValue,
                'change' => $previousWon > 0 ? round(($wonValue - $previousWon) / $previousWon * 100) : null,
                'won_count' => $wonCount,
                'average' => $wonCount > 0 ? $wonValue / $wonCount : 0.0,
                'lost_value' => (float) $deals()->whereIn('leads.stage_id', $lostIds)->where('leads.closed_at', '>=', $since)->sum('properties.price'),
                'win_rate' => ($wonCount + $lostCount) > 0 ? round($wonCount / ($wonCount + $lostCount) * 100, 1) : null,
                // Open pipeline: every not-yet-closed lead on a listing, at that listing's price.
                'pipeline_value' => (float) $deals()->where(fn ($q) => $q->whereNull('leads.stage_id')->orWhereNotIn('leads.stage_id', $closedStages->pluck('id')->all() ?: [0]))->sum('properties.price'),
                'pipeline_count' => $deals()->where(fn ($q) => $q->whereNull('leads.stage_id')->orWhereNotIn('leads.stage_id', $closedStages->pluck('id')->all() ?: [0]))->count(),
            ],
            'overTime' => $this->perDay($won()->where('leads.closed_at', '>=', $since), 'leads.closed_at', $days, 'SUM(properties.price)'),
            'byListingType' => $won()->where('leads.closed_at', '>=', $since)
                ->selectRaw('properties.listing_type, SUM(properties.price) as total')->groupBy('properties.listing_type')
                ->pluck('total', 'listing_type')->mapWithKeys(fn ($total, $type) => [ucfirst($type ?: 'Other') => (float) $total]),
            'recentDeals' => $won()->where('leads.closed_at', '>=', $since)
                ->with(['agent:id,name', 'stage:id,name'])
                ->select('leads.id', 'leads.name', 'leads.agent_id', 'leads.stage_id', 'leads.closed_at', 'properties.id as property_id',
                    'properties.translations as property_translations', 'properties.reference_no', 'properties.listing_type', 'properties.price')
                ->orderByDesc('leads.closed_at')->limit(10)->get(),
            'byAgent' => $viewer?->type === 'company' || !$viewer
                ? $won()->where('leads.closed_at', '>=', $since)->whereNotNull('leads.agent_id')
                    ->join('portal_users as agents', 'agents.id', '=', 'leads.agent_id')
                    ->selectRaw('agents.name, SUM(properties.price) as total, count(*) as deals')
                    ->groupBy('agents.id', 'agents.name')->orderByDesc('total')->limit(10)->get()
                : collect(),
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
