<?php

namespace App\Http\Controllers\Portal;

use App\Models\Property;
use App\Services\ListingComplianceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Portal › Listings › Listing Approvals (Super Admin only): review each agency / agent listing's
 * DLD advertising permit, Madmoun QR and Form A before it can go live. Rules: ListingComplianceService.
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

        $tab = array_key_exists($request->input('tab'), Property::COMPLIANCE_LABELS) ? $request->input('tab') : Property::COMPLIANCE_PENDING;
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

    public function approve(Request $request, $id)
    {
        $this->authorizeAdmin();
        $data = $request->validate(['note' => 'nullable|string|max:2000']);
        $property = Property::findOrFail($id);

        if ($property->isSold()) {
            return back()->with('error', 'This listing is marked ' . $property->sold_type . ' — nothing to approve.');
        }
        if ($missing = $this->compliance->missingItems($property)) {
            return back()->with('error', 'Cannot approve yet. Missing: ' . implode(', ', $missing) . '.');
        }

        $this->compliance->approve($property, $data['note'] ?? null);

        return redirect()->route('portal.listing-approvals.index')->with('toast', 'Listing approved and published.');
    }

    /** Send back to the agent / agency — also how a live listing is taken down (e.g. a DLD complaint). */
    public function requestChanges(Request $request, $id)
    {
        $this->authorizeAdmin();
        $data = $request->validate(['note' => 'required|string|max:2000']);
        $property = Property::findOrFail($id);

        $this->compliance->requestChanges($property, $data['note']);

        return redirect()->route('portal.listing-approvals.index')->with('toast', 'Changes requested — the listing is off the website until it is resubmitted.');
    }
}
