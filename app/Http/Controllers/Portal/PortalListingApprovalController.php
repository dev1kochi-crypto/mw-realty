<?php

namespace App\Http\Controllers\Portal;

use App\Models\Property;
use App\Services\ListingComplianceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Portal › Listings › Listing Permits (Super Admin only): which listings' advertising permits are
 * verified. The agency / agent validates the permit in the property form (DLD / ADREC). Where Super
 * Admin approval applies (LISTING_SUPERADMIN_APPROVAL on, or a DTCM / None permit) the listing waits
 * here to be approved; otherwise a verified listing goes live by itself. Super Admin can take any
 * listing down. Rules: ListingComplianceService.
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

        // With approval on, open on the listings waiting for it.
        $defaultTab = config('permits.superadmin_approval') ? Property::COMPLIANCE_PENDING : Property::COMPLIANCE_APPROVED;
        $tab = array_key_exists($request->input('tab'), Property::COMPLIANCE_LABELS) ? $request->input('tab') : $defaultTab;
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

    /** Approve a listing waiting for Super Admin (PermitRules::needsApproval) — it goes live. */
    public function approve($id)
    {
        $this->authorizeAdmin();
        $property = Property::findOrFail($id);

        if ($property->isSold()) {
            return back()->with('error', 'This listing is marked ' . $property->sold_type . ' — nothing to approve.');
        }
        if ($property->compliance_status !== Property::COMPLIANCE_PENDING) {
            return back()->with('error', 'Only listings waiting for approval can be approved.');
        }
        if ($missing = $this->compliance->missingItems($property)) {
            return back()->with('error', 'Cannot approve yet. Missing: ' . implode(', ', $missing) . '.');
        }

        $this->compliance->approve($property);

        return redirect()->route('portal.listing-approvals.index', ['tab' => Property::COMPLIANCE_PENDING])->with('toast', 'Listing approved — it is live on the website.');
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
