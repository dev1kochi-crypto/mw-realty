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

class DashboardController extends Controller
{
    public function index()
    {
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
            'crm_leads' => Enquiry::whereNotNull('portal_user_id')->count(),
            'new_crm_leads' => Enquiry::whereNotNull('portal_user_id')->where('status', 'new')->count(),
            'total_plans' => Plan::count(),
        ];

        $stats['monthly_revenue'] = PortalUser::approved()
            ->whereHas('plan', fn ($q) => $q->where('billing_cycle', 'monthly')->where('price', '>', 0))
            ->with('plan')
            ->get()
            ->sum(fn ($u) => (float) $u->plan->price);

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
        $leadStatusBreakdown = Enquiry::whereNotNull('portal_user_id')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

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
            ->withCount(['properties', 'enquiries'])
            ->orderByDesc('properties_count')
            ->take(5)
            ->get();

        $recentLeads = Enquiry::whereNotNull('portal_user_id')
            ->with(['property', 'owner'])
            ->latest()
            ->take(5)
            ->get();

        $latestProperties = Property::with('owner')->latest()->take(5)->get();

        $paidUserPoints = PortalUser::approved()
            ->whereHas('plan', fn ($q) => $q->where('price', '>', 0))
            ->with('plan')
            ->orderBy('created_at')
            ->get(['id', 'created_at', 'plan_id'])
            ->map(fn ($u) => ['date' => $u->created_at, 'price' => (float) $u->plan->price])
            ->values();

        $revenueSeries = [
            'daily' => $this->buildRevenueSeries($paidUserPoints, now()->subDays(29)->startOfDay(), 30, 'day', 'd M'),
            'weekly' => $this->buildRevenueSeries($paidUserPoints, now()->subWeeks(11)->startOfWeek(), 12, 'week', 'd M'),
            'monthly' => $this->buildRevenueSeries($paidUserPoints, now()->subMonths(11)->startOfMonth(), 12, 'month', 'M Y'),
        ];

        return view('cms-kit::dashboard', compact(
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
        ));
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
