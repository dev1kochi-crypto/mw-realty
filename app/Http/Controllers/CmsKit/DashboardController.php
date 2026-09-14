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
use App\Services\Crm\LeadService;

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

        $topOwners = PortalUser::approved()
            ->withCount(['properties', 'leads'])
            ->orderByDesc('properties_count')
            ->take(5)
            ->get();

        $recentLeads = $this->leadService->filteredQuery(null)->take(5)->get();

        $latestProperties = Property::with('owner')->latest()->take(5)->get();

        $paidUserPoints = PortalUser::query()->join('plans', 'plans.id', '=', 'portal_users.plan_id')
            ->where('portal_users.status', 'approved')->where('portal_users.is_active', true)
            ->where('plans.status', true)->where('plans.billing_cycle', 'monthly')
            ->selectRaw('DATE(portal_users.created_at) as joined_on, SUM(plans.price) as price')
            ->groupByRaw('DATE(portal_users.created_at)')->orderBy('joined_on')->get()
            ->map(fn ($point) => ['date' => \Carbon\Carbon::parse($point->joined_on), 'price' => (float) $point->price])->values();

        $revenueSeries = [
            'daily' => $this->buildRevenueSeries($paidUserPoints, now()->subDays(29)->startOfDay(), 30, 'day', 'd M'),
            'weekly' => $this->buildRevenueSeries($paidUserPoints, now()->subWeeks(11)->startOfWeek(), 12, 'week', 'd M'),
            'monthly' => $this->buildRevenueSeries($paidUserPoints, now()->subMonths(11)->startOfMonth(), 12, 'month', 'M Y'),
        ];

        return compact(
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
            'noPlan',
            'revenueSeries'
        );
    }

    /**
     * Cumulative MRR at each bucket, approximated from currently-assigned
     * paid plans against each subscriber's join date (no historical
     * subscription/invoice log exists to derive this precisely).
     */
    private function buildRevenueSeries($points, \Carbon\Carbon $start, int $count, string $unit, string $labelFormat): array
    {
        $labels = [];
        $data = [];
        $cursor = $start->copy();
        $cumulative = 0.0;
        $idx = 0;
        $total = $points->count();

        for ($i = 0; $i < $count; $i++) {
            $bucketEnd = match ($unit) {
                'day' => $cursor->copy()->endOfDay(),
                'week' => $cursor->copy()->endOfWeek(),
                'month' => $cursor->copy()->endOfMonth(),
            };

            while ($idx < $total && $points[$idx]['date'] <= $bucketEnd) {
                $cumulative += $points[$idx]['price'];
                $idx++;
            }

            $labels[] = $cursor->format($labelFormat);
            $data[] = round($cumulative, 2);

            $cursor = match ($unit) {
                'day' => $cursor->addDay(),
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonth(),
            };
        }

        return ['labels' => $labels, 'data' => $data];
    }
}
