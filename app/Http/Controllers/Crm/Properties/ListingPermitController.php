<?php

namespace App\Http\Controllers\Crm\Properties;

use App\Http\Controllers\Crm\Concerns\ScopesPortalOwner;
use App\Models\Filter;
use App\Models\FilterValue;
use App\Models\Property;
use App\Services\ListingComplianceService;
use App\Support\PermitRules;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

/**
 * @group CRM Listing Permits
 *
 * Super Admin only: which listings' advertising permits are verified. The agency / agent validates
 * the permit in the property form (DLD / ADREC). Where Super Admin approval applies
 * (LISTING_SUPERADMIN_APPROVAL on, or a DTCM / None permit) the listing waits here to be approved;
 * otherwise a verified listing goes live by itself. Super Admin can take any listing down.
 * Rules: ListingComplianceService.
 */
class ListingPermitController extends Controller
{
    use ScopesPortalOwner;

    private const PER_PAGE = 20;

    public function __construct(private readonly ListingComplianceService $compliance)
    {
    }

    private function authorizeAdmin(): void
    {
        abort_unless($this->isAdmin(), 403);
    }

    /** Status labels (the pending one depends on whether Super Admin approval is on). */
    private function labels(): array
    {
        return [Property::COMPLIANCE_PENDING => config('permits.superadmin_approval') ? 'Awaiting approval' : 'Not verified / approval'] + Property::COMPLIANCE_LABELS;
    }

    /**
     * List listings by permit status
     *
     * 20 per page, the most recently submitted / saved first, with the count per status.
     *
     * @queryParam tab string draft | pending | changes_requested | approved | expired. Example: pending
     * @queryParam q string Title, ref, permit no, agency or agent. Example: marina
     * @queryParam page integer Example: 1
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();
        $approvalOn = (bool) config('permits.superadmin_approval');
        // With approval on, open on the listings waiting for it.
        $defaultTab = $approvalOn ? Property::COMPLIANCE_PENDING : Property::COMPLIANCE_APPROVED;
        $tab = array_key_exists($request->input('tab'), Property::COMPLIANCE_LABELS) ? $request->input('tab') : $defaultTab;
        $search = mb_substr(trim((string) $request->input('q', '')), 0, 100);

        $listings = Property::query()
            ->with(['owner', 'agent'])
            ->available()
            ->where('compliance_status', $tab)
            ->when($search !== '', fn ($q) => $q->where(fn ($s) => $s->portalSearch($search, true)->orWhere('permit_number', 'like', '%' . $search . '%')))
            // Latest first: the most recently submitted / saved listing at the top.
            ->orderByRaw('COALESCE(compliance_submitted_at, updated_at) DESC')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        return response()->json([
            'data' => collect($listings->items())->map(function (Property $p) use ($tab) {
                $when = $tab === Property::COMPLIANCE_APPROVED ? $p->compliance_reviewed_at : $p->compliance_submitted_at;

                return [
                    'id' => $p->id,
                    'title' => $p->getTranslation('title'),
                    'thumb' => $p->galleryImages()[0]['url'] ?? null,
                    'reference_no' => $p->reference_no,
                    'price' => ($p->currency ?: 'AED') . ' ' . number_format((float) $p->price),
                    'purpose' => $p->listing_type === 'rent' ? 'Rent' : 'Sale',
                    'owner' => $p->owner?->displayName() ?? 'MW Realty',
                    'agent' => $p->agent && $p->agent_id !== $p->portal_user_id ? $p->agent->name : null,
                    'permit_number' => $p->permit_number,
                    'permit_expires_at' => $p->permit_expires_at?->toDateString(),
                    'days_left' => $p->permit_expires_at ? (int) today()->diffInDays($p->permit_expires_at, false) : null,
                    'validates' => PermitRules::validates($p->permit_type),
                    'verified' => match (true) {
                        $p->permit_verified_at && in_array($p->permit_verified_via, ['dld', 'adrec'], true) => 'online',
                        (bool) $p->permit_verified_at => 'manual',
                        default => null,
                    },
                    'issuer' => PermitRules::issuer($p->permit_type),
                    'no_permit_needed' => $p->permit_type && !PermitRules::requiresPermit($p->permit_type),
                    'awaiting_approval' => $p->awaitingApproval(),
                    'when' => $when?->toIso8601String(),
                ];
            }),
            'meta' => [
                'current_page' => $listings->currentPage(), 'last_page' => $listings->lastPage(), 'total' => $listings->total(),
                'from' => $listings->firstItem(), 'to' => $listings->lastItem(),
            ],
            'tab' => $tab,
            'search' => $search,
            'approval_on' => $approvalOn,
            'labels' => $this->labels(),
            'counts' => Property::query()->available()->selectRaw('compliance_status, COUNT(*) as total')->groupBy('compliance_status')->pluck('total', 'compliance_status'),
        ]);
    }

    /**
     * A listing's permit review
     *
     * The permit, what the authority's record says next to the ad, the checklist, the review
     * history, and which action applies (approve / take down).
     */
    public function show($id)
    {
        $this->authorizeAdmin();
        $property = Property::with(['owner', 'agent', 'complianceLogs'])->findOrFail($id);
        $type = $property->permit_type;
        $needsPermit = PermitRules::requiresPermit($type);
        $issuer = PermitRules::issuer($type) ?? 'Permit';
        $expired = $property->permit_expires_at && $property->permit_expires_at->lt(today());
        $verifiedOnline = $property->permit_verified_at && in_array($property->permit_verified_via, ['dld', 'adrec'], true);
        $optionLabel = fn (string $key, $value) => $value === null ? null
            : (FilterValue::whereHas('filter', fn ($q) => $q->where('key', $key))->where('value', $value)->first()?->getTranslation('label') ?? $value);
        $permitData = $property->permit_data ?? [];

        return response()->json([
            'id' => $property->id,
            'title' => $property->getTranslation('title') ?: 'Property',
            'thumb' => $property->galleryImages()[0]['url'] ?? null,
            'reference_no' => $property->reference_no,
            'segment' => $property->segment,
            'owner' => $property->owner?->displayName() ?? 'MW Realty',
            'agent' => $property->agent && $property->agent_id !== $property->portal_user_id ? $property->agent->name : null,
            'compliance_status' => $property->compliance_status,
            'compliance_label' => $property->complianceLabel(),
            'is_sold' => $property->isSold(),
            'sold_type' => $property->sold_type,
            'awaiting_approval' => $property->awaitingApproval(),
            'emirate' => $optionLabel(Filter::EMIRATE_KEY, $property->emirate) ?? ($property->emirate ? Str::headline($property->emirate) : '—'),
            'permit' => [
                'type' => $type,
                'type_label' => PermitRules::TYPES[$type]['label'] ?? '—',
                'city' => $property->permit_city ? (PermitRules::NORTHERN_CITIES[$property->permit_city] ?? $property->permit_city) : null,
                'needs_permit' => $needsPermit,
                'issuer' => $issuer,
                'validates' => PermitRules::validates($type),
                'license_label' => $needsPermit && (PermitRules::TYPES[$type]['license'] ?? null) ? PermitRules::TYPES[$type]['license_label'] : null,
                'license_no' => $property->permit_license_no ?: (PermitRules::license($type, $property->owner)['number'] ?? '—'),
                'number' => $property->permit_number,
                'expires_at' => $property->permit_expires_at?->toDateString(),
                'expired' => $expired,
                'qr' => $property->permit_qr ? media_url($property->permit_qr) : null,
                'verification_url' => $property->permit_verification_url,
                'verified_at' => $property->permit_verified_at?->toIso8601String(),
                'verified_online' => $verifiedOnline,
            ],
            // What the authority's record says vs what the ad says (only when the permit was verified online).
            'comparison' => $permitData ? collect([
                'Category' => [$optionLabel('category', $permitData['category'] ?? null), $optionLabel('category', $property->category)],
                'Purpose' => [$optionLabel('listing_type', $permitData['listing_type'] ?? null), $optionLabel('listing_type', $property->listing_type)],
                'Type' => [$optionLabel('property_type', $permitData['property_type'] ?? null), $optionLabel('property_type', $property->property_type)],
                'Location' => [$permitData['zone_name'] ?? $optionLabel('location', $permitData['location'] ?? null), $optionLabel('location', $property->location) ?? $property->getTranslation('community')],
                'Bedrooms' => [$permitData['bedrooms'] ?? null, $property->bedrooms],
                'Size (sq.ft)' => [isset($permitData['sqft']) ? number_format($permitData['sqft']) : null, $property->sqft ? number_format($property->sqft) : null],
                'Price' => [isset($permitData['price']) ? number_format((float) $permitData['price']) : null, number_format((float) $property->price)],
            ])->map(fn ($pair, $label) => ['label' => $label, 'permit' => $pair[0], 'ad' => $pair[1], 'mismatch' => $pair[0] !== null && (string) $pair[0] !== (string) $pair[1]])->values() : null,
            'facts' => collect([
                'Purpose' => $property->listing_type === 'rent' ? 'For Rent' : 'For Sale',
                'Type' => $property->filterLabel('property_type') ?: '—',
                'Price' => ($property->currency ?: 'AED') . ' ' . number_format((float) $property->price),
                'Bedrooms' => $property->bedrooms ?? '—',
                'Size' => $property->sqft ? number_format($property->sqft) . ' sq.ft' : '—',
                'Location' => $property->getTranslation('community') ?: $property->getTranslation('city') ?: '—',
                'Agency ORN' => $property->owner?->orn_number ?: '—',
                'Agent BRN' => $property->agent?->brn_number ?: '—',
            ])->map(fn ($value, $label) => ['label' => $label, 'value' => (string) $value])->values(),
            'checks' => collect($needsPermit ? array_filter([
                "{$issuer} permit number" => (bool) $property->permit_number,
                'Permit valid (not expired)' => $property->permit_expires_at && !$expired,
                'Permit QR code' => PermitRules::requiresQr($type) ? (bool) $property->permit_qr : null,
                'Permit verified' => PermitRules::validates($type) ? (bool) $property->permit_verified_at : null,
            ], fn ($v) => $v !== null) : ['No permit needed (' . (PermitRules::TYPES[$type]['label'] ?? 'not set') . ')' => (bool) $type])
                ->map(fn ($ok, $label) => ['label' => $label, 'ok' => $ok])->values(),
            'missing' => $this->compliance->missingItems($property),
            'reviewed_at' => ($property->compliance_reviewed_at ?? $property->permit_verified_at)?->toIso8601String(),
            'logs' => $property->complianceLogs->map(fn ($log) => [
                'status' => $log->to_status,
                'label' => Property::COMPLIANCE_LABELS[$log->to_status] ?? $log->to_status,
                'actor' => $log->actorName(),
                'note' => $log->note,
                'at' => $log->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * Approve & publish
     *
     * A listing waiting for Super Admin (PermitRules::needsApproval) — it goes live.
     */
    public function approve($id)
    {
        $this->authorizeAdmin();
        $property = Property::findOrFail($id);

        if ($property->isSold()) {
            return $this->failed('This listing is marked ' . $property->sold_type . ' — nothing to approve.');
        }
        if ($property->compliance_status !== Property::COMPLIANCE_PENDING) {
            return $this->failed('Only listings waiting for approval can be approved.');
        }
        if ($missing = $this->compliance->missingItems($property)) {
            return $this->failed('Cannot approve yet. Missing: ' . implode(', ', $missing) . '.');
        }

        $this->compliance->approve($property);

        return response()->json(['success' => true, 'message' => 'Listing approved — it is live on the website.']);
    }

    /**
     * Take a listing down
     *
     * Off the website (wrong details, a DLD complaint …) until its permit details are changed.
     */
    public function takeDown($id)
    {
        $this->authorizeAdmin();
        $this->compliance->takeDown(Property::findOrFail($id));

        return response()->json(['success' => true, 'message' => 'Listing taken down — it stays off the website until its permit details are changed.']);
    }

    private function failed(string $message)
    {
        return response()->json(['success' => false, 'message' => $message], 422);
    }
}
