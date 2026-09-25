<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Routing\Controller;
use App\Models\CmsKit\Banner;
use App\Models\CmsKit\Faq;
use App\Models\CmsKit\Enquiry;
use App\Models\CmsKit\Testimonial;
use App\Models\CmsKit\Career;
use App\Models\Property;
use App\Models\PortalUser;
use App\Models\Plan;
use App\Models\Lead;
use App\Models\PlanPayment;
use App\Models\PlanUpgradeRequest;
use App\Services\Crm\LeadService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function __construct(private readonly LeadService $leadService)
    {
    }

    public function index()
    {
        if (!auth('cms')->user()->hasRole('superadmin')) return view('cms-kit::dashboard-restricted');
        $data = \Illuminate\Support\Facades\Cache::remember('admin.dashboard.summary', 30, fn () => $this->dashboardData());
        return view('cms-kit::dashboard', $data);
    }

    private function dashboardData(): array
    {
        $leadStats = $this->leadService->getLeadStatistics(null);

        $stats = [
            'banners' => Banner::count(),
            'faqs' => Faq::count(),
            'enquiries' => Enquiry::count(),
            'testimonials' => Testimonial::count(),
            'careers' => class_exists(Career::class) ? Career::count() : 0,

            'total_properties' => Property::count(),
            'active_properties' => Property::active()->count(),
            'approved_agents' => PortalUser::where('type', 'agent')->approved()->count(),
            'approved_companies' => PortalUser::where('type', 'company')->approved()->count(),
            'pending_accounts' => PortalUser::pending()->count(),
            'rejected_accounts' => PortalUser::where('status', 'rejected')->count(),
            'crm_leads' => $leadStats['total'],
            'new_crm_leads' => $leadStats['active'],
            'total_plans' => Plan::count(),
        ];

        $stats['monthly_revenue'] = (float) PortalUser::query()->join('plans', 'plans.id', '=', 'portal_users.plan_id')
            ->where('portal_users.status', 'approved')->where('portal_users.is_active', true)
            ->where('plans.status', true)->where('plans.billing_cycle', 'monthly')->sum('plans.price');

        $totalAccounts = $stats['approved_agents'] + $stats['approved_companies'] + $stats['pending_accounts'] + $stats['rejected_accounts'];
        $stats['approval_rate'] = $totalAccounts > 0
            ? round((($stats['approved_agents'] + $stats['approved_companies']) / $totalAccounts) * 100)
            : 0;

        $paidSubscribers = PortalUser::approved()->whereHas('plan', fn ($q) => $q->where('price', '>', 0))->count();
        $freeSubscribers = PortalUser::approved()->whereNotNull('plan_id')->whereHas('plan', fn ($q) => $q->where('price', 0))->count();
        $noPlan = PortalUser::approved()->whereNull('plan_id')->count();

        // Actionable — not a duplicate list: pending accounts waiting on a decision.
        $pendingAccounts = PortalUser::pending()->latest()->take(5)->get();

        // CRM pipeline (New -> Contacted -> Closed)
        $leadStatusBreakdown = $leadStats;

        $listingTypeBreakdown = Property::whereNotNull('listing_type')
            ->selectRaw('listing_type, count(*) as total')
            ->groupBy('listing_type')
            ->pluck('total', 'listing_type');

        $propertyTypeBreakdown = Property::whereNotNull('property_type')
            ->selectRaw('property_type, count(*) as total')
            ->groupBy('property_type')
            ->orderByDesc('total')
            ->pluck('total', 'property_type');

        $planDistribution = Plan::active()
            ->withCount(['subscribers' => fn ($q) => $q->approved()])
            ->orderBy('order_index')
            ->get();

        $topOwners = PortalUser::approved()->with('plan')
            ->withCount(['properties', 'leads'])
            ->orderByDesc('properties_count')
            ->take(5)
            ->get();

        $recentLeads = $this->leadService->filteredQuery(null)->take(5)->get();

        $latestProperties = Property::with('owner')->latest()->take(5)->get();

        // Period-over-period: last 30 days vs the 30 before, for the KPI deltas.
        $now = now();
        $curStart = $now->copy()->subDays(30);
        $prevStart = $now->copy()->subDays(60);
        $window = fn ($query) => [
            'current' => (clone $query)->where('created_at', '>=', $curStart)->count(),
            'previous' => (clone $query)->whereBetween('created_at', [$prevStart, $curStart])->count(),
        ];
        $trends = [
            'leads' => $window(Lead::query()),
            'properties' => $window(Property::query()),
            'signups' => $window(PortalUser::query()),
            'revenue' => [
                'current' => (float) PlanPayment::paid()->where('paid_at', '>=', $curStart->toDateString())->sum('amount'),
                'previous' => (float) PlanPayment::paid()->whereBetween('paid_at', [$prevStart->toDateString(), $curStart->toDateString()])->sum('amount'),
            ],
        ];

        // 12-week activity, bucketed in PHP so it stays DB-driver agnostic (these tables are small).
        $weeksStart = $now->copy()->subWeeks(11)->startOfWeek();
        $weekPoints = fn ($query) => $query->where('created_at', '>=', $weeksStart)->pluck('created_at')
            ->map(fn ($d) => ['date' => Carbon::parse($d), 'value' => 1]);
        $activity = [
            'leads' => $this->bucket($weekPoints(Lead::query()), $weeksStart, 12, 'week', 'd M'),
            'properties' => $this->bucket($weekPoints(Property::query()), $weeksStart, 12, 'week', 'd M'),
            'signups' => $this->bucket($weekPoints(PortalUser::query()), $weeksStart, 12, 'week', 'd M'),
        ];

        // Collected revenue — actual paid invoices, as opposed to the MRR estimate above.
        $payments = PlanPayment::paid()->whereNotNull('paid_at')
            ->where('paid_at', '>=', $now->copy()->subMonths(11)->startOfMonth()->toDateString())
            ->get(['paid_at', 'amount'])
            ->map(fn ($p) => ['date' => Carbon::parse($p->paid_at), 'value' => (float) $p->amount]);
        $collectedSeries = [
            'daily' => $this->bucket($payments, $now->copy()->subDays(29)->startOfDay(), 30, 'day', 'd M'),
            'weekly' => $this->bucket($payments, $weeksStart, 12, 'week', 'd M'),
            'monthly' => $this->bucket($payments, $now->copy()->subMonths(11)->startOfMonth(), 12, 'month', 'M Y'),
        ];

        // Pipeline by stage. Stages are per-owner rows, so same-named stages are merged across owners.
        $stagePipeline = Lead::query()
            ->join('lead_stages', 'lead_stages.id', '=', 'leads.stage_id')
            ->selectRaw('lead_stages.name, MIN(lead_stages.order_index) as sort, MAX(lead_stages.is_closed) as is_closed, COUNT(leads.id) as total')
            ->groupBy('lead_stages.name')
            ->orderBy('sort')
            ->get();
        $unstagedLeads = Lead::whereNull('stage_id')->count();

        $leadSources = Lead::query()
            ->join('lead_sources', 'lead_sources.id', '=', 'leads.source_id')
            ->selectRaw('lead_sources.name, COUNT(leads.id) as total')
            ->groupBy('lead_sources.name')
            ->orderByDesc('total')
            ->pluck('total', 'name');
        $unsourcedLeads = Lead::whereNull('source_id')->count();

        $accountStatus = [
            'approved' => $stats['approved_agents'] + $stats['approved_companies'],
            'pending' => $stats['pending_accounts'],
            'rejected' => $stats['rejected_accounts'],
        ];

        $attention = [
            'upgrade_requests' => PlanUpgradeRequest::pending()->count(),
            'failed_payments' => PlanPayment::where('status', 'failed')->where('created_at', '>=', $curStart)->count(),
            'recent_enquiries' => Enquiry::where('created_at', '>=', $now->copy()->subDays(7))->count(),
            'inactive_properties' => $stats['total_properties'] - $stats['active_properties'],
        ];

        $stats['featured_properties'] = Property::where('featured', true)->count();

        return compact(
            'trends',
            'activity',
            'collectedSeries',
            'stagePipeline',
            'unstagedLeads',
            'leadSources',
            'unsourcedLeads',
            'accountStatus',
            'attention',
            'stats',
            'pendingAccounts',
            'leadStatusBreakdown',
            'listingTypeBreakdown',
            'propertyTypeBreakdown',
            'planDistribution',
            'topOwners',
            'recentLeads',
            'latestProperties',
            'paidSubscribers',
            'freeSubscribers',
            'noPlan'
        );
    }

    /**
     * Sums each point's value into fixed day/week/month buckets starting at $start.
     */
    private function bucket(Collection $points, Carbon $start, int $count, string $unit, string $labelFormat): array
    {
        $labels = [];
        $edges = [];
        $data = array_fill(0, $count, 0);
        $cursor = $start->copy();

        for ($i = 0; $i < $count; $i++) {
            $edges[] = [$cursor->copy(), match ($unit) {
                'day' => $cursor->copy()->endOfDay(),
                'week' => $cursor->copy()->endOfWeek(),
                'month' => $cursor->copy()->endOfMonth(),
            }];
            $labels[] = $cursor->format($labelFormat);
            $cursor = match ($unit) {
                'day' => $cursor->addDay(),
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonth(),
            };
        }

        foreach ($points as $point) {
            foreach ($edges as $i => [$from, $to]) {
                if ($point['date']->between($from, $to)) {
                    $data[$i] += $point['value'];
                    break;
                }
            }
        }

        return ['labels' => $labels, 'data' => array_map(fn ($v) => round($v, 2), $data)];
    }
}
