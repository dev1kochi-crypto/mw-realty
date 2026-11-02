<?php

namespace App\Http\Controllers\Crm\Properties;

use App\Http\Controllers\Crm\Concerns\ScopesPortalOwner;
use App\Models\ListingReview;
use App\Models\Property;
use App\Services\ListingReview\ListingReviewService;
use App\Support\PermitRules;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @group CRM Listing Review
 *
 * Super Admin only: listings the automated screening flagged (ListingReviewService) — a unit another
 * agency already lists (shown side by side with the price spread; the first listing stays live, newer
 * ones wait) and listings with risk signals (price far off the area, ad ≠ permit, resubmitted). Each
 * waiting listing is approved (goes on the website) or rejected (stays off until the agency fixes it).
 */
class ListingReviewController extends Controller
{
    use ScopesPortalOwner;

    private const PER_PAGE = 20;

    private const TABS = ['duplicate', 'risk', 'resolved'];

    public function __construct(private readonly ListingReviewService $reviews)
    {
    }

    private function authorizeAdmin(): void
    {
        abort_unless($this->isAdmin(), 403);
    }

    /**
     * List review cases
     *
     * 20 per page, newest first, with the count per tab and the screening measures.
     *
     * @queryParam tab string duplicate | risk | resolved. Example: duplicate
     * @queryParam q string Title, ref, agency or agent of any listing in the case. Example: marina
     * @queryParam page integer Example: 1
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();
        $tab = in_array($request->input('tab'), self::TABS, true) ? $request->input('tab') : 'duplicate';
        $search = mb_substr(trim((string) $request->input('q', '')), 0, 100);

        $cases = ListingReview::query()
            ->when($tab === 'resolved', fn ($q) => $q->where('status', ListingReview::RESOLVED), fn ($q) => $q->open()->where('type', $tab))
            ->when($search !== '', fn ($q) => $q->whereHas('properties', fn ($p) => $p->portalSearch($search, true)))
            ->with(['properties' => fn ($q) => $q->with(['owner', 'agent'])])
            ->orderByDesc($tab === 'resolved' ? 'resolved_at' : 'updated_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        return response()->json([
            'data' => collect($cases->items())->map(fn (ListingReview $case) => $this->summary($case)),
            'meta' => [
                'current_page' => $cases->currentPage(), 'last_page' => $cases->lastPage(), 'total' => $cases->total(),
                'from' => $cases->firstItem(), 'to' => $cases->lastItem(),
            ],
            'tab' => $tab,
            'search' => $search,
            'counts' => [
                'duplicate' => ListingReview::open()->where('type', ListingReview::DUPLICATE)->count(),
                'risk' => ListingReview::open()->where('type', ListingReview::RISK)->count(),
                'resolved' => ListingReview::where('status', ListingReview::RESOLVED)->count(),
            ],
            'metrics' => $this->reviews->metrics(),
        ]);
    }

    /**
     * A review case
     *
     * Every listing in the case side by side (what differs is marked), why each was matched, the
     * price spread, the risk signals, and the case's outcome once resolved.
     */
    public function show($id)
    {
        $this->authorizeAdmin();
        $case = ListingReview::with(['properties' => fn ($q) => $q->with(['owner', 'agent', 'details'])->orderBy('properties.created_at')->orderBy('properties.id'), 'resolver'])->findOrFail($id);
        $members = $case->properties;
        $open = $case->status === ListingReview::OPEN;

        $listings = $members->map(function (Property $p) use ($case, $open) {
            $rate = $p->sqft > 0 && $p->price > 0 ? (float) $p->price / $p->sqft : null;

            return [
                'id' => $p->id,
                'title' => $p->getTranslation('title') ?: 'Property',
                'reference_no' => $p->reference_no,
                'segment' => $p->segment,
                'photos' => array_slice(array_column($p->galleryImages(), 'url'), 0, 40),
                'photo_count' => count($p->galleryNumbers()),
                'outcome' => $p->pivot->outcome,
                'note' => $p->pivot->note,
                // Waiting for a decision (a newer copy, or a risk case's listing — held or only flagged) …
                'waiting' => $open && $p->pivot->outcome === null && ($p->pivot->held || $case->type === ListingReview::RISK),
                'held' => $open && $p->pivot->outcome === null && (bool) $p->pivot->held,
                // … or the first listing of the unit, which stays on the website meanwhile.
                'original' => $case->type === ListingReview::DUPLICATE && $p->pivot->outcome === null && !$p->pivot->held,
                'reasons' => json_decode((string) $p->pivot->reasons, true) ?: [],
                'website' => match (true) {
                    $p->isSold() => ucfirst((string) $p->sold_type),
                    (bool) $p->reviewRejection() => 'Rejected — off the website',
                    $p->review_hold => 'Waiting for review — off the website',
                    (bool) $p->status => 'Live',
                    default => 'Offline · ' . $p->complianceLabel(),
                },
                'live' => (bool) $p->status,
                'price_value' => (float) $p->price,
                'price_rate' => $rate,
                // Compared across the listings: the same value everywhere = no discrepancy.
                'facts' => [
                    'Advertiser' => $p->owner?->displayName() ?? 'MW Realty',
                    'Agency ORN' => $p->owner?->orn_number ?: '—',
                    'Agent' => $p->agent?->name ?? '—',
                    'Agent BRN' => $p->agent?->brn_number ?: '—',
                    'Purpose' => $p->listing_type === 'rent' ? 'Rent' : 'Sale',
                    'Type' => $p->filterLabel('property_type') ?: '—',
                    'Bedrooms' => (string) ($p->bedrooms ?? '—'),
                    'Bathrooms' => (string) ($p->bathrooms ?? '—'),
                    'Size' => $p->sqft ? number_format($p->sqft) . ' sq.ft' : '—',
                    'Unit number' => $p->details?->unit_number ?: '—',
                    'Floor' => $p->details?->floor ?: '—',
                    'Community' => $p->getTranslation('community') ?: $p->getTranslation('city') ?: '—',
                    'Address' => $p->getTranslation('address') ?: '—',
                    'Permit no.' => $p->permit_number ?: '—',
                ],
                'permit' => [
                    // Listing Permits (its own section): approved there or not yet.
                    'status' => $p->compliance_status,
                    'label' => $p->complianceLabel(),
                    'approved' => $p->compliance_status === Property::COMPLIANCE_APPROVED,
                    'issuer' => PermitRules::issuer($p->permit_type),
                    'verified_online' => $p->permit_verified_at && in_array($p->permit_verified_via, ['dld', 'adrec'], true),
                    'verified' => (bool) $p->permit_verified_at,
                    'expires_at' => $p->permit_expires_at?->toDateString(),
                    'expired' => $p->permit_expires_at && $p->permit_expires_at->lt(today()),
                ],
                'kyc_approved' => $p->owner ? $p->owner->isApproved() : true,
                'price' => ($p->currency ?: 'AED') . ' ' . number_format((float) $p->price),
                'rate' => $rate ? ($p->currency ?: 'AED') . ' ' . number_format($rate) . ' / sq.ft' : null,
                'listed_at' => $p->created_at?->toIso8601String(),
            ];
        })->values();

        $compared = $listings->where('outcome', '!=', 'rejected')->count() >= 2 ? $listings->where('outcome', '!=', 'rejected') : $listings;
        $labels = collect(array_keys($listings->first()['facts'] ?? []));
        $differs = $labels->filter(fn ($label) => $compared->pluck("facts.{$label}")->unique()->count() > 1)->values();
        // The same (and filled in) everywhere — only meaningful with two or more listings side by side.
        $same = $compared->count() >= 2
            ? $labels->filter(fn ($label) => $compared->pluck("facts.{$label}")->unique()->count() === 1 && $compared->first()['facts'][$label] !== '—')->values()
            : collect();

        return response()->json([
            'id' => $case->id,
            'type' => $case->type,
            'status' => $case->status,
            'resolution' => $case->resolution,
            'resolution_note' => $case->resolution_note,
            'resolved_at' => $case->resolved_at?->toIso8601String(),
            'resolver' => $case->resolver?->name,
            'created_at' => $case->created_at?->toIso8601String(),
            'signals' => $case->signals ?? [],
            // Price risk: the similar listings it was compared with, to check by hand.
            'price_check' => $case->type === ListingReview::RISK && collect($case->signals)->pluck('code')->intersect(['price_low', 'price_high'])->isNotEmpty()
                ? $this->reviews->priceComparison($members->first())
                : null,
            'price' => $this->priceSpread($compared),
            'differs' => $differs,
            'same' => $same,
            'listings' => $listings,
        ]);
    }

    /**
     * Approve one listing
     *
     * It may be on the website. In a duplicate case both agencies then advertise the unit, each
     * with its own permit, and the two are never flagged against each other again.
     */
    public function approve(Request $request, $id)
    {
        $this->authorizeAdmin();
        $data = $request->validate(['property_id' => 'required|integer', 'note' => 'nullable|string|max:1000']);
        $this->reviews->approve(ListingReview::findOrFail($id), (int) $data['property_id'], $data['note'] ?? null);
        $property = Property::find((int) $data['property_id']);

        return response()->json(['success' => true, 'message' => $property && !$property->isSold() && $property->compliance_status !== Property::COMPLIANCE_APPROVED
            ? 'Approved here — it goes on the website once its permit is approved on Listing Permits (' . strtolower($property->complianceLabel()) . ').'
            : 'Approved — the listing can be on the website.']);
    }

    /**
     * Reject one listing
     *
     * Off the website with the reason; once the agency fixes it and saves, it comes back here.
     */
    public function reject(Request $request, $id)
    {
        $this->authorizeAdmin();
        $data = $request->validate(['property_id' => 'required|integer', 'note' => 'required|string|max:1000']);
        $this->reviews->reject(ListingReview::findOrFail($id), (int) $data['property_id'], $data['note']);

        return response()->json(['success' => true, 'message' => 'Rejected — the listing stays off the website and the agency was told why.']);
    }

    private function summary(ListingReview $case): array
    {
        $members = $case->properties;

        return [
            'id' => $case->id,
            'type' => $case->type,
            'status' => $case->status,
            'resolution' => $case->resolution,
            'created_at' => $case->created_at?->toIso8601String(),
            'resolved_at' => $case->resolved_at?->toIso8601String(),
            'signals' => array_column($case->signals ?? [], 'label'),
            'price' => $this->priceSpread($members->map(fn (Property $p) => ['price_value' => (float) $p->price, 'outcome' => $p->pivot->outcome])->where('outcome', '!=', 'rejected')),
            'held' => $members->filter(fn ($p) => $p->pivot->outcome === null && ($p->pivot->held || $case->type === ListingReview::RISK))->count(),
            // Risk flags that don't hold: the listing stays on the website while it waits.
            'live_flagged' => $case->type === ListingReview::RISK && $members->contains(fn ($p) => $p->pivot->outcome === null && !$p->pivot->held),
            // Also not approved on Listing Permits yet (permit not verified / waiting for approval …).
            'permit_waiting' => $members->filter(fn ($p) => $p->pivot->outcome !== 'rejected' && !$p->isSold() && $p->compliance_status !== Property::COMPLIANCE_APPROVED)->count(),
            'listings' => $members->map(fn (Property $p) => [
                'id' => $p->id,
                'title' => $p->getTranslation('title') ?: 'Property',
                'reference_no' => $p->reference_no,
                'thumb' => $p->galleryImages()[0]['url'] ?? null,
                'owner' => $p->owner?->displayName() ?? 'MW Realty',
                'price' => ($p->currency ?: 'AED') . ' ' . number_format((float) $p->price),
                'outcome' => $p->pivot->outcome,
            ])->values(),
        ];
    }

    /** Lowest / highest price across the listings still compared, and the spread between them. */
    private function priceSpread($listings): ?array
    {
        $prices = collect($listings)->pluck('price_value')->filter(fn ($v) => $v > 0);
        if ($prices->count() < 2) {
            return null;
        }
        $min = $prices->min();
        $max = $prices->max();
        $variance = round(($max - $min) * 100 / $min, 1);

        return [
            'min' => number_format($min),
            'max' => number_format($max),
            'variance_pct' => $variance,
            'conflict' => $variance >= ListingReviewService::PRICE_VARIANCE_PCT,
        ];
    }
}
