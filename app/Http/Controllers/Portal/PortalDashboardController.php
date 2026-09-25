<?php

namespace App\Http\Controllers\Portal;

use App\Models\Lead;
use App\Models\PortalUser;
use App\Models\Property;
use App\Services\Crm\LeadService;
use App\Services\Crm\OwnerContext;
use Carbon\Carbon;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;

class PortalDashboardController extends Controller
{
    public function __construct(
        private readonly OwnerContext $ownerContext,
        private readonly LeadService $leadService,
    ) {
    }

    public function index()
    {
        $isAdmin = $this->ownerContext->isAdmin();
        $ownerId = $this->ownerContext->ownerId();
        $owner = $this->ownerContext->owner();

        // Every query below is scoped to the signed-in agent/company; Super Admin (ownerId null) sees everything.
        $propertyQuery = fn () => Property::when($ownerId, fn ($q) => $q->where('portal_user_id', $ownerId));
        $leadQuery = fn () => Lead::forOwner($ownerId);

        $leadStats = $this->leadService->getLeadStatistics($ownerId);

        $stats = [
            'total_properties' => $propertyQuery()->count(),
            'active_properties' => $propertyQuery()->where('status', true)->count(),
            'featured_properties' => $propertyQuery()->where('featured', true)
                ->where(fn ($q) => $q->whereNull('featured_until')->orWhere('featured_until', '>=', now()))->count(),
            'total_leads' => $leadStats['total'],
            'new_leads' => $leadStats['active'],
        ];
        $stats['inactive_properties'] = $stats['total_properties'] - $stats['active_properties'];

        // Last 30 days vs the 30 before, for the KPI deltas.
        $now = now();
        $curStart = $now->copy()->subDays(30);
        $prevStart = $now->copy()->subDays(60);
        $window = fn ($query) => [
            'current' => (clone $query)->where('created_at', '>=', $curStart)->count(),
            'previous' => (clone $query)->whereBetween('created_at', [$prevStart, $curStart])->count(),
        ];
        $trends = [
            'leads' => $window($leadQuery()),
            'properties' => $window($propertyQuery()),
        ];

        // 12-week activity (bucketed in PHP so it stays DB-driver agnostic).
        $weeksStart = $now->copy()->subWeeks(11)->startOfWeek();
        $weekPoints = fn ($query) => $query->where('created_at', '>=', $weeksStart)->pluck('created_at')->map(fn ($d) => Carbon::parse($d));
        $activity = [
            'leads' => $this->bucketWeeks($weekPoints($leadQuery()), $weeksStart),
            'properties' => $this->bucketWeeks($weekPoints($propertyQuery()), $weeksStart),
        ];

        // Pipeline by stage — stages are per-owner rows, so same-named stages merge in the global view.
        $stagePipeline = $leadQuery()
            ->join('lead_stages', 'lead_stages.id', '=', 'leads.stage_id')
            ->selectRaw('lead_stages.name, MIN(lead_stages.order_index) as sort, MAX(lead_stages.is_closed) as is_closed, MAX(lead_stages.color) as color, COUNT(leads.id) as total')
            ->groupBy('lead_stages.name')
            ->orderBy('sort')
            ->get();
        $unstagedLeads = $leadQuery()->whereNull('stage_id')->count();

        $wonLeads = $stagePipeline->filter(fn ($s) => $s->is_closed && !str_contains(strtolower($s->name), 'lost'))->sum('total');
        $lostLeads = $stagePipeline->filter(fn ($s) => $s->is_closed && str_contains(strtolower($s->name), 'lost'))->sum('total');
        $pipeline = [
            'open' => $stagePipeline->where('is_closed', 0)->sum('total') + $unstagedLeads,
            'won' => $wonLeads,
            'lost' => $lostLeads,
            'win_rate' => ($wonLeads + $lostLeads) > 0 ? round($wonLeads / ($wonLeads + $lostLeads) * 100) : null,
        ];

        $leadSources = $leadQuery()
            ->join('lead_sources', 'lead_sources.id', '=', 'leads.source_id')
            ->selectRaw('lead_sources.name, COUNT(leads.id) as total')
            ->groupBy('lead_sources.name')
            ->orderByDesc('total')
            ->pluck('total', 'name');
        $unsourcedLeads = $leadQuery()->whereNull('source_id')->count();

        // Listings that attract the most enquiries.
        $topListings = $propertyQuery()->withCount('leads')->having('leads_count', '>', 0)
            ->orderByDesc('leads_count')->take(5)->get();

        $propertyTypeBreakdown = $propertyQuery()->whereNotNull('property_type')
            ->selectRaw('property_type, count(*) as total')->groupBy('property_type')->orderByDesc('total')
            ->pluck('total', 'property_type');
        $listingTypeBreakdown = $propertyQuery()->whereNotNull('listing_type')
            ->selectRaw('listing_type, count(*) as total')->groupBy('listing_type')
            ->pluck('total', 'listing_type');

        // Leads sitting in the default ("New") stage for over 2 days — nobody has picked them up yet.
        $staleLeads = $leadQuery()
            ->where(fn ($q) => $q->whereNull('stage_id')->orWhereHas('stage', fn ($s) => $s->where('is_default', true)))
            ->where('created_at', '<=', $now->copy()->subDays(2))
            ->count();

        $featuredExpiring = $propertyQuery()->where('featured', true)
            ->whereBetween('featured_until', [$now, $now->copy()->addDays(7)])->count();

        // Plan usage (agents/companies only — Super Admin has no plan).
        $planUsage = null;
        if ($owner) {
            $owner->loadMissing('plan');
            $plan = $owner->plan;
            $remaining = $owner->remainingPropertySlots();
            $limit = ($plan && !$plan->isUnlimited()) ? (int) $plan->property_limit : null;
            $planUsage = [
                'name' => $plan?->getTranslation('name') ?? 'No plan',
                'limit' => $limit,
                'used' => $stats['total_properties'],
                'remaining' => $remaining,
                'pct' => $limit ? min(100, round($stats['total_properties'] / max($limit, 1) * 100)) : null,
                'agents' => $owner->type === 'company' ? $owner->agents()->count() : null,
                'agents_remaining' => $owner->type === 'company' ? $owner->remainingAgentSlots() : null,
                'reports' => $owner->hasReportsAccess(),
            ];
        }

        // Super Admin: which partners receive the most leads.
        $topOwners = $isAdmin
            ? $this->leadService->getLeadsByOwner()->sortByDesc('total')->take(5)->values()
            : collect();

        $recentProperties = $propertyQuery()->with('owner')->latest()->take(5)->get();
        $recentLeads = $this->leadService->filteredQuery($ownerId)->with('property')->take(6)->get();

        $deltas = ['leads' => $this->delta($trends['leads']), 'properties' => $this->delta($trends['properties'])];
        $isApproved = $isAdmin || ($owner && $owner->status === 'approved');
        $alerts = $this->alerts($stats, $staleLeads, $featuredExpiring, $planUsage);
        $shortcuts = $this->shortcuts($isAdmin, $isApproved, $owner);

        // Lead sources with a fixed colour per slot (Unknown always grey, last).
        $palette = ['#ca2844', '#244373', '#1f8a8a', '#d9923b', '#7a5ea8', '#5b8fd1', '#9aa1b8'];
        $sourceRows = $leadSources->map(fn ($t, $n) => ['name' => $n, 'total' => (int) $t])->values();
        if ($unsourcedLeads > 0) {
            $sourceRows->push(['name' => 'Unknown', 'total' => $unsourcedLeads]);
        }
        $sourceRows = $sourceRows->values()->map(fn ($r, $i) => $r + ['color' => $r['name'] === 'Unknown' ? '#c9cddb' : $palette[min($i, count($palette) - 1)]]);

        // Listing showcase: most-enquired first, topped up with the latest listings.
        $showcase = $topListings->concat($recentProperties->whereNotIn('id', $topListings->pluck('id')))->take(4)->values();

        // When enquiries arrive: leads per weekday over the last 90 days (Mon..Sun).
        $weekdayLeads = array_fill(0, 7, 0);
        foreach ($leadQuery()->where('created_at', '>=', $now->copy()->subDays(90))->pluck('created_at') as $d) {
            $weekdayLeads[Carbon::parse($d)->dayOfWeekIso - 1]++;
        }
        $heroImage = asset('frontend/assets/images/home/hero-bg.jpg');

        return view('portal.dashboard.index', compact(
            'stats', 'trends', 'activity', 'stagePipeline', 'unstagedLeads', 'pipeline',
            'leadSources', 'unsourcedLeads', 'topListings', 'propertyTypeBreakdown', 'listingTypeBreakdown',
            'staleLeads', 'featuredExpiring', 'planUsage', 'topOwners',
            'recentProperties', 'recentLeads', 'isAdmin', 'isApproved', 'alerts', 'shortcuts',
            'sourceRows', 'showcase', 'deltas', 'weekdayLeads', 'heroImage'
        ));
    }

    /** Last-30-days vs prior-30-days change, as a display-ready badge. */
    private function delta(array $t): array
    {
        if ($t['previous'] == 0) {
            return $t['current'] > 0 ? ['dir' => 'up', 'text' => 'New'] : ['dir' => 'flat', 'text' => '0%'];
        }
        $pct = (int) round(($t['current'] - $t['previous']) / $t['previous'] * 100);

        return ['dir' => $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat'), 'text' => ($pct > 0 ? '+' : '') . $pct . '%'];
    }

    private function alerts(array $stats, int $staleLeads, int $featuredExpiring, ?array $planUsage): array
    {
        $alerts = [];
        if ($planUsage && $planUsage['limit'] !== null && $planUsage['remaining'] === 0) {
            $alerts[] = ['count' => $planUsage['used'] . '/' . $planUsage['limit'], 'label' => 'listing limit reached', 'icon' => 'fa-gauge-high', 'tone' => 'amber', 'url' => route('portal.plans.index')];
        }
        $candidates = [
            [$staleLeads, str('lead')->plural($staleLeads) . ' waiting 2+ days', 'fa-hourglass-half', 'red', route('portal.crm.leads.index')],
            [$stats['new_leads'], 'active ' . str('lead')->plural($stats['new_leads']), 'fa-bell', 'navy', route('portal.crm.leads.index', ['status' => 'active'])],
            [$featuredExpiring, 'featured expiring in 7 days', 'fa-star', 'amber', route('portal.properties.index')],
            [$stats['inactive_properties'], 'inactive ' . str('listing')->plural($stats['inactive_properties']), 'fa-eye-slash', 'grey', route('portal.properties.index')],
        ];
        foreach ($candidates as [$count, $label, $icon, $tone, $url]) {
            if ($count > 0) {
                $alerts[] = compact('count', 'label', 'icon', 'tone', 'url');
            }
        }

        return $alerts;
    }

    /** Shortcuts limited to pages this account type can actually open. */
    private function shortcuts(bool $isAdmin, bool $isApproved, ?PortalUser $owner): array
    {
        $links = [];
        if ($isApproved) {
            $links[] = ['label' => 'Add Property', 'icon' => 'fa-plus', 'tone' => 'red', 'url' => route('portal.properties.create')];
            $links[] = ['label' => 'Leads', 'icon' => 'fa-address-book', 'tone' => 'navy', 'url' => route('portal.crm.leads.index')];
            $links[] = ['label' => 'Properties', 'icon' => 'fa-building', 'tone' => 'teal', 'url' => route('portal.properties.index')];
            $links[] = ['label' => 'Import Leads', 'icon' => 'fa-file-import', 'tone' => 'violet', 'url' => route('portal.crm.leads.import.form')];
            $links[] = ['label' => 'Reports', 'icon' => 'fa-chart-pie', 'tone' => 'green', 'url' => route('portal.crm.reports.index')];
            $links[] = ['label' => 'Stages & Tags', 'icon' => 'fa-sliders', 'tone' => 'amber', 'url' => route('portal.crm.master.stages.index')];
            if ($isAdmin || $owner?->type === 'company') {
                $links[] = ['label' => $isAdmin ? 'Agents' : 'Team Agents', 'icon' => 'fa-users', 'tone' => 'teal', 'url' => route('portal.agents.index')];
            }
        }
        if ($owner) {
            $links[] = ['label' => 'My Profile', 'icon' => 'fa-id-card', 'tone' => 'navy', 'url' => route('portal.profile.edit')];
            $links[] = ['label' => 'Plans & Billing', 'icon' => 'fa-layer-group', 'tone' => 'green', 'url' => route('portal.plans.index')];
        }
        if ($isAdmin) {
            $links[] = ['label' => 'Nearby Places', 'icon' => 'fa-location-dot', 'tone' => 'red', 'url' => route('portal.nearby-places.index')];
        }

        return $links;
    }

    /** Counts dates into 12 consecutive weeks starting at $start. */
    private function bucketWeeks(Collection $dates, Carbon $start): array
    {
        $labels = [];
        $data = array_fill(0, 12, 0);
        for ($i = 0; $i < 12; $i++) {
            $labels[] = $start->copy()->addWeeks($i)->format('d M');
        }
        foreach ($dates as $date) {
            $i = (int) floor($start->diffInDays($date, false) / 7);
            if ($i >= 0 && $i < 12) {
                $data[$i]++;
            }
        }

        return ['labels' => $labels, 'data' => $data];
    }
}
