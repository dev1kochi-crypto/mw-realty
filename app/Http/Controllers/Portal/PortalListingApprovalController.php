<?php

namespace App\Http\Controllers\Portal;

use App\Models\Property;
use App\Services\ListingComplianceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Portal › Listings › Listing Permits (Super Admin only): an overview of which listings' advertising
 * permits are verified. Super Admin doesn't verify anything — the agency / agent validates the permit
 * in the property form (DLD / ADREC) and a verified listing goes live by itself; not-verified or expired
 * ones stay off the website. Super Admin can still take a listing down. Rules: ListingComplianceService.
 */
class PortalListingApprovalController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(private readonly ListingComplianceService $compliance)
    {
    }

    private function authorizeAdmin(): void
    {
        abort_unless(!Auth::guard('portal')->check() && Auth::guard('cms')->user()?->hasRole('superadmin'), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $tab = array_key_exists($request->input('tab'), Property::COMPLIANCE_LABELS) ? $request->input('tab') : Property::COMPLIANCE_APPROVED;
        $search = trim((string) $request->input('q', ''));

        $listings = Property::query()
            ->with(['owner', 'agent'])
            ->available()
            ->where('compliance_status', $tab)
            ->when($search !== '', fn ($q) => $q->where(fn ($s) => $s
                ->portalSearch($search, true)
                ->orWhere('permit_number', 'like', '%' . $search . '%')))
            // Oldest submission first, so nobody waits longest.
            ->orderByRaw('compliance_submitted_at IS NULL')
            ->orderBy('compliance_submitted_at')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $counts = Property::query()->available()
            ->selectRaw('compliance_status, COUNT(*) as total')
            ->groupBy('compliance_status')
            ->pluck('total', 'compliance_status');

        return view('portal.listing-approvals.index', compact('listings', 'counts', 'tab', 'search'));
    }

    public function show($id)
    {
        $this->authorizeAdmin();
        $property = Property::with(['owner', 'agent', 'complianceLogs'])->findOrFail($id);

        return view('portal.listing-approvals.show', [
            'property' => $property,
            'missing' => $this->compliance->missingItems($property),
            'routePrefix' => $property->segment === Property::SEGMENT_COMMERCIAL ? 'portal.commercial' : 'portal.properties',
        ]);
    }

    /** Take a listing off the website (wrong details, a DLD complaint …). */
    public function takeDown($id)
    {
        $this->authorizeAdmin();
        $property = Property::findOrFail($id);

        $this->compliance->takeDown($property);

        return redirect()->route('portal.listing-approvals.index')->with('toast', 'Listing taken down — it stays off the website until its permit details are changed.');
    }
}
