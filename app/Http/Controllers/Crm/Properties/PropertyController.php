<?php

namespace App\Http\Controllers\Crm\Properties;

use App\Http\Controllers\Crm\Concerns\ScopesListings;
use App\Http\Requests\PropertyRequest;
use App\Models\CmsKit\Language;
use App\Models\Filter;
use App\Models\FilterValue;
use App\Models\PortalUser;
use App\Models\Property;
use App\Models\PropertyDetail;
use App\Services\FeaturedListingService;
use App\Services\ListingComplianceService;
use App\Services\ListingQualityService;
use App\Services\Properties\ListingFormData;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * @group CRM Properties
 *
 * Properties and Commercial listings — the same table, form and screens, split only by `segment`
 * (residential | commercial); plan property limits count both. Super Admin sees every listing; an
 * Agent / Company sees and can only touch their own (an agency agent also the agency listings
 * assigned to them — view / edit only).
 */
class PropertyController extends Controller
{
    use ScopesListings;

    /** Listings per page on the card grid — a multiple of the 4-column row so pages end on a full row. */
    public const PER_PAGE = 16;

    /** Permit text / date inputs (Core details; the QR file is saved by ListingComplianceService::storeUploads()). */
    private const COMPLIANCE_INPUTS = ['permit_number', 'permit_expires_at', 'permit_verification_url'];

    public function __construct(private readonly ListingFormData $form)
    {
    }

    /** residential (default) | commercial, from the request. */
    private function segment(Request $request): string
    {
        return $request->input('segment') === Property::SEGMENT_COMMERCIAL ? Property::SEGMENT_COMMERCIAL : Property::SEGMENT_RESIDENTIAL;
    }

    private function itemLabel(string $segment): string
    {
        return $segment === Property::SEGMENT_COMMERCIAL ? 'Commercial Property' : 'Property';
    }

    /**
     * List listings
     *
     * One page (16) of a menu's listings in display order, with the search / filters, the DLD permit
     * review counts, plan usage and premium quota.
     *
     * @queryParam segment string residential | commercial. Example: residential
     * @queryParam q string Title, ref no, RERA, address, community or city (and agent / agency for Super Admin). Example: marina
     * @queryParam agent string Agent id, or "none" = agency listings with no agent. Example: 12
     * @queryParam listing string sale | rent. Example: rent
     * @queryParam status string active | inactive. Example: active
     * @queryParam premium boolean Example: false
     * @queryParam review string A permit review state (draft, pending, changes_requested, approved, expired). Example: pending
     * @queryParam page integer Example: 1
     */
    public function index(Request $request, FeaturedListingService $featured, ListingQualityService $quality)
    {
        // Also applied by the scheduled properties:expire-featured command; running it here keeps
        // this page correct even where the scheduler isn't running (e.g. local dev).
        $featured->sync();
        $segment = $this->segment($request);
        $search = mb_substr(trim((string) $request->input('q', '')), 0, 100);

        // details / floorPlans feed the quality score (ListingQualityService), leads_count the Leads figure.
        $query = $this->orderedListings($segment)->with(['owner', 'agent:id,name,avatar', 'details', 'floorPlans'])->withCount('leads');
        if ($search !== '') {
            $query->portalSearch($search, $this->isAdmin());
        }

        // Filters: agent ("none" = agency's own, no agent), buy / rent, active / inactive, premium.
        $filters = [
            'agent' => $request->input('agent') === 'none' ? 'none' : ($request->integer('agent') ?: null),
            'listing' => in_array($request->input('listing'), ['sale', 'rent'], true) ? $request->input('listing') : null,
            'status' => in_array($request->input('status'), ['active', 'inactive'], true) ? $request->input('status') : null,
            'premium' => $request->boolean('premium'),
            // DLD permit review state (see ListingComplianceService).
            'review' => array_key_exists((string) $request->input('review'), Property::COMPLIANCE_LABELS) ? $request->input('review') : null,
        ];
        $query->when($filters['agent'] === 'none', fn ($q) => $q->whereNull('properties.agent_id'))
            ->when(is_int($filters['agent']), fn ($q) => $q->where('properties.agent_id', $filters['agent']))
            ->when($filters['listing'], fn ($q, $type) => $q->where('properties.listing_type', $type))
            ->when($filters['status'], fn ($q, $status) => $q->where('properties.status', $status === 'active'))
            ->when($filters['premium'], fn ($q) => $q->where('properties.featured', true))
            ->when($filters['review'], fn ($q, $review) => $q->where('properties.compliance_status', $review));

        $properties = $query->paginate(self::PER_PAGE);
        $viewer = $this->viewer();
        $editable = $featured->editableIds(
            $this->isAdmin() ? null : $viewer,
            collect($properties->items())->filter(fn ($p) => $p->featured || $p->isFeatureScheduled())->pluck('id'),
        );

        $planUsage = null;
        if (!$this->isAdmin()) {
            $owner = $viewer->listingOwner();
            $planUsage = $owner->plan ? [
                'plan' => $owner->plan->getTranslation('name'),
                'limit' => $owner->plan->property_limit,
                'used' => $owner->properties()->count(),
                'remaining' => $owner->remainingPropertySlots(),
            ] : null;
        }
        $quota = $this->isAdmin() ? null : $featured->quota($viewer);

        return response()->json([
            'data' => collect($properties->items())->map(fn (Property $p) => $this->cardJson($p, $quality, isset($editable[$p->id]))),
            'meta' => [
                'current_page' => $properties->currentPage(),
                'last_page' => $properties->lastPage(),
                'per_page' => $properties->perPage(),
                'total' => $properties->total(),
                'from' => $properties->firstItem(),
                'to' => $properties->lastItem(),
            ],
            'segment' => $segment,
            'search' => $search,
            'filters' => $filters,
            'filtered' => (bool) array_filter($filters),
            'filter_agent' => is_int($filters['agent']) ? PortalUser::find($filters['agent'], ['id', 'name']) : null,
            // DLD permit review counts over this menu's listings, for the strip above the grid.
            'review_counts' => $this->orderedListings($segment)->reorder()
                ->selectRaw('properties.compliance_status, COUNT(*) as total')
                ->groupBy('properties.compliance_status')
                ->pluck('total', 'compliance_status'),
            'review_labels' => [Property::COMPLIANCE_PENDING => config('permits.superadmin_approval') ? 'Awaiting approval' : 'Not verified / approval'] + Property::COMPLIANCE_LABELS,
            // Agent filter only makes sense for an agency (its agents) or Super Admin (everyone's).
            'can_filter_agent' => $this->isAdmin() || $viewer?->isAgency(),
            // Whole list size (unfiltered), for the "Move to position" picker.
            'total_listings' => $this->orderedListings($segment)->count(),
            'is_admin' => $this->isAdmin(),
            'plan_usage' => $planUsage,
            'featured_quota' => $quota ? $quota + ['resets' => now()->addMonthNoOverflow()->startOfMonth()->format('d M')] : null,
            'feature_max_days' => FeaturedListingService::MAX_DAYS,
        ]);
    }

    /** One listing card / list row. */
    private function cardJson(Property $p, ListingQualityService $quality, bool $featureEditable): array
    {
        $score = $quality->score($p);
        // The Location tab's required Address/Community/City fields live per-language in
        // translations; the bare `location` column is only the optional location filter slug.
        $address = collect(['address', 'community', 'city'])->map(fn ($part) => $p->getTranslation($part))->filter()->implode(', ')
            ?: ($p->location ? ($p->filterLabel('location') ?: $p->location) : null);
        $cardAgent = $p->agent ?? ($p->owner?->type === 'agent' ? $p->owner : null);

        return [
            'id' => $p->id,
            'segment' => $p->segment,
            'title' => $p->getTranslation('title'),
            'thumb' => $p->galleryImages()[0]['url'] ?? null,
            'reference_no' => $p->reference_no,
            'listing_type' => $p->listing_type,
            'listing_label' => $p->filterLabel('listing_type'),
            'type_label' => $p->filterLabel('property_type'),
            'owner_label' => $p->owner ? ($p->owner->type === 'company' ? ($p->owner->company_name ?: $p->owner->name) : $p->owner->name) : 'MW Realty',
            'address' => $address,
            'short_address' => collect(['community', 'city'])->map(fn ($part) => $p->getTranslation($part))->filter()->implode(', '),
            'bedrooms' => $p->bedrooms,
            'bathrooms' => $p->bathrooms,
            'sqft' => $p->sqft,
            'price' => $p->price ? (float) $p->price : null,
            'currency' => $p->currency,
            'status' => (bool) $p->status,
            'featured' => (bool) $p->featured,
            'scheduled' => $p->isFeatureScheduled(),
            'featured_from' => $p->featured_from?->format('Y-m-d'),
            'featured_until' => $p->featured_until?->format('Y-m-d'),
            'feature_editable' => $featureEditable,
            'compliance_status' => $p->compliance_status,
            'compliance_label' => $p->complianceLabel(),
            'live' => $p->canGoLive() || $p->isSold(),
            'review' => $this->reviewBar($p),
            'quality' => $score,
            'leads_count' => (int) $p->leads_count,
            'agent' => $cardAgent ? ['name' => $cardAgent->name, 'avatar' => media_url($cardAgent->avatar)] : null,
        ];
    }

    /** The card's one-line permit bar while the listing isn't live: [icon, text, call to action]. */
    private function reviewBar(Property $p): array
    {
        [$icon, $text, $cta] = $p->awaitingImportReview() ? ['fa-user-shield', 'Imported from Property Finder — in MW Realty review', 'View']
            : ($p->awaitingApproval() ? ['fa-user-shield', 'Waiting for MW Realty to approve it', 'View'] : match ($p->compliance_status) {
                Property::COMPLIANCE_CHANGES_REQUESTED => ['fa-rotate-left', 'Taken down by MW Realty — update and validate the permit', 'Fix now'],
                Property::COMPLIANCE_EXPIRED => ['fa-ban', 'Permit expired — add the renewed permit', 'Renew'],
                Property::COMPLIANCE_PENDING => ['fa-shield-halved', 'Permit not verified — validate it to go live', 'Validate'],
                default => ['fa-file-circle-exclamation', 'Add the permit details to go live', 'Add'],
            });

        return ['icon' => $icon, 'text' => $text, 'cta' => $cta];
    }

    /**
     * A listing's detail page
     *
     * Gallery, headline facts, description per language, details, amenities, floor plans, location
     * and nearby places, agent, listing info and SEO.
     */
    public function show($id)
    {
        $property = $this->findAccessible($id);
        $property->load(['agent.company', 'nearbyPlaces']);
        $detail = $property->details;
        $chipLabel = fn ($row) => $row['label'][app()->getLocale()] ?? ($row['label']['en'] ?? (is_array($row['label'] ?? null) ? reset($row['label']) : ''));
        $iconUrl = fn ($icon) => $icon ? (str_starts_with($icon, 'http') ? $icon : media_url($icon)) : null;
        $languages = Language::where('status', true)->get();
        $agent = $property->agent;

        return response()->json([
            'id' => $property->id,
            'segment' => $property->segment,
            'title' => $property->getTranslation('title'),
            'reference_no' => $property->reference_no,
            'gallery' => array_column($property->galleryImages(), 'url'),
            'listing_label' => $property->filterLabel('listing_type'),
            'status' => (bool) $property->status,
            'featured' => (bool) $property->featured,
            'price' => $property->price ? number_format($property->price) . ' ' . $property->currency : 'Price on request',
            'address' => collect(['address', 'community', 'city', 'country'])->map(fn ($part) => $property->getTranslation($part))->filter()->implode(', '),
            'stats' => array_values(array_filter([
                ['fa-bed', $property->bedrooms, 'Bedrooms'],
                ['fa-bath', $property->bathrooms, 'Bathrooms'],
                ['fa-ruler-combined', $property->sqft ? number_format($property->sqft) : null, 'Sq.ft'],
                ['fa-square-parking', $detail?->parking, 'Parking'],
                ['fa-warehouse', $detail?->garage, 'Garage'],
                ['fa-calendar-alt', $detail?->year_built, 'Year Built'],
            ], fn ($s) => filled($s[1]))),
            'languages' => $languages->map(function ($lang) use ($property) {
                $t = $property->translations[$lang->code] ?? [];

                return [
                    'code' => $lang->code,
                    'name' => $lang->name,
                    'title' => $t['title'] ?? null,
                    'highlights' => collect(preg_split('/\r\n|\r|\n/', (string) ($t['key_features'] ?? '')))->map(fn ($l) => trim($l, " \t-•*"))->filter()->values(),
                    'description' => \App\Support\SafeHtml::clean($t['description'] ?? null),
                    'address' => $t['address'] ?? null,
                    'community' => $t['community'] ?? null,
                    'city' => $t['city'] ?? null,
                    'country' => $t['country'] ?? null,
                ];
            })->values(),
            'facts' => array_values(array_filter([
                ['fa-tag', 'Listing Type', $property->filterLabel('listing_type')],
                ['fa-building', 'Property Type', $property->filterLabel('property_type')],
                ['fa-drafting-compass', 'Completion', $property->filterLabel('completion_status')],
                ['fa-layer-group', 'Category', $property->category ? ($property->filterLabel('category') ?: $property->category) : null],
                ['fa-map-signs', 'Location', $property->location ? ($property->filterLabel('location') ?: $property->location) : null],
                ['fa-couch', 'Furnishing', $detail?->furnishingLabel()],
                ['fa-mountain', 'View', $detail?->view],
                ['fa-stairs', 'Floor', $detail?->floor],
                ['fa-user-check', 'Direct From Owner', $detail?->direct_from_owner],
                ['fa-shield-alt', 'Security Deposit', $detail?->security_deposit ? number_format($detail->security_deposit) . ' ' . $property->currency : null],
                ['fa-id-card', 'RERA ID', $property->rera_id],
                ['fa-file-shield', 'DLD Permit', $property->permit_number ? $property->permit_number . ($property->permit_expires_at ? ' (expires ' . $property->permit_expires_at->format('d M Y') . ')' : '') : null],
                ['fa-list-check', 'Permit Review', $property->complianceLabel()],
                ['fa-hashtag', 'Reference', $property->reference_no],
                ['fa-envelope-open-text', 'Postal Code', $property->postal_code],
                ['fa-clock', 'Published', $property->published_at?->format('d M Y, h:i A')],
                ['fa-link', 'Slug', $property->slug],
                ['fa-sort-numeric-down', 'Display Order', $property->order_index],
            ], fn ($f) => filled($f[2]))),
            'chip_groups' => $detail ? array_values(array_filter(array_map(fn ($g) => [
                'label' => $g[0],
                'icon' => $g[1],
                'rows' => collect($g[2] ?? [])->map(fn ($row) => ['label' => $chipLabel($row), 'icon' => $iconUrl($row['icon'] ?? null)])->values(),
            ], [
                ['Amenities', 'fa-swimming-pool', $detail->amenities],
                ['Easy Access', 'fa-route', $detail->easy_access],
                ['Attributes', 'fa-list-check', $detail->property_attributes],
            ]), fn ($g) => $g['rows']->isNotEmpty())) : [],
            'floor_plan_file' => $detail?->floor_plan_file ? media_url($detail->floor_plan_file) : null,
            'floor_plans' => $property->floorPlans->map(fn ($plan) => [
                'label' => $plan->label,
                'image' => $plan->image ? media_url($plan->image) : null,
                'size_from' => $plan->size_from,
                'size_to' => $plan->size_to,
                'price_from' => $plan->price_from ? number_format($plan->price_from) : null,
                'price_to' => $plan->price_to ? number_format($plan->price_to) : null,
            ])->values(),
            'currency' => $property->currency,
            'latitude' => $property->latitude,
            'longitude' => $property->longitude,
            'nearby_groups' => $property->nearbyPlaces->groupBy(fn ($place) => $place->typeLabel() ?: 'Other')
                ->map(fn ($places, $type) => ['type' => $type, 'places' => $places->map(fn ($p) => $p->getTranslation('name') ?? '—')->values()])->values(),
            'agent' => $agent ? [
                'name' => $agent->name,
                'avatar' => media_url($agent->avatar),
                'company' => $agent->company?->displayName(),
                'brn_number' => $agent->brn_number,
                'phone' => $agent->phone,
                'whatsapp' => $agent->whatsapp_number,
                'email' => $agent->email,
            ] : null,
            'is_admin' => $this->isAdmin(),
            'owner_label' => $property->owner ? ($property->owner->type === 'company' ? $property->owner->displayName() : $property->owner->name) : 'MW Realty',
            'virtual_tour_url' => $detail?->virtual_tour_url,
            'created_at' => $property->created_at?->format('d M Y, h:i A'),
            'updated_at' => $property->updated_at?->format('d M Y, h:i A'),
            'seo' => array_filter($property->metadata ?? [], fn ($v) => is_string($v) && trim($v) !== ''),
        ]);
    }

    /**
     * Form options for a new listing
     *
     * The form's option data (ListingFormData) plus the next reference number. 403 until the account
     * is approved; 422 once the plan's property limit is reached.
     *
     * @queryParam segment string residential | commercial. Example: residential
     */
    public function create(Request $request)
    {
        if (!$this->isAdmin() && $this->viewer()->status !== 'approved') {
            abort(403, 'Your account needs to be approved by Super Admin before you can add properties. Complete your profile while you wait for review.');
        }
        $data = $this->form->for(null);
        if ($data['remaining_slots'] === 0) {
            abort(422, "You've reached your plan's property limit. Upgrade your plan to add more listings.");
        }

        return response()->json($data + ['reference_no' => Property::nextReferenceNo(), 'segment' => $this->segment($request)]);
    }

    /**
     * A listing for the edit form
     *
     * Every saved value, plus the form's option data (ListingFormData) and why it isn't live yet.
     */
    public function edit($id, ListingComplianceService $compliance)
    {
        $property = $this->findAccessible($id);
        $details = $property->details;
        $verifiedVia = $property->permit_verified_at ? $property->permit_verified_via : null;

        return response()->json($this->form->for($property) + ['property' => [
            'id' => $property->id,
            'segment' => $property->segment,
            'title' => $property->getTranslation('title'),
            'values' => [
                ...$property->only(['reference_no', 'rera_id', 'slug', 'listing_type', 'completion_status', 'property_type', 'category', 'location',
                    'emirate', 'rental_period', 'postal_code', 'bedrooms', 'bathrooms', 'sqft', 'currency', 'order_index', 'permit_number', 'permit_verification_url', 'permit_city', 'agent_id']),
                'latitude' => $property->latitude !== null ? (string) (float) $property->latitude : '',
                'longitude' => $property->longitude !== null ? (string) (float) $property->longitude : '',
                'price' => $property->price !== null ? (string) (float) $property->price : '',
                'status' => (bool) $property->status,
                'published_at' => $property->published_at?->format('Y-m-d\TH:i'),
                'available_dates' => collect($property->available_dates ?? [])->filter()->sort()->values(),
                'permit_type' => in_array($property->permit_type, \App\Support\PermitRules::DUBAI_TYPES, true) ? $property->permit_type : 'rera',
                'permit_expires_at' => $property->permit_expires_at?->format('Y-m-d'),
                'translations' => (object) ($property->translations ?? []),
                'metadata' => (object) collect($property->metadata ?? [])->except('og_image')->all(),
                ...collect(['developer', 'unit_number', 'owner_name', 'video_tour_url', 'cheques', 'year_built', 'floor', 'parking', 'garage', 'furnished', 'direct_from_owner', 'security_deposit', 'virtual_tour_url', 'view'])
                    ->mapWithKeys(fn ($f) => [$f => $details?->{$f}])->all(),
                'upgraded' => (bool) $details?->upgraded,
                ...collect(Filter::ICON_LISTS)->mapWithKeys(fn ($column) => [$column => collect($details?->{$column} ?? [])->pluck('key')->filter()->map(fn ($v) => (string) $v)->values()])->all(),
                'nearby_places' => $property->nearbyPlaces->map(fn ($place) => ['id' => $place->id, 'name' => $place->getTranslation('name')])->values(),
                'floor_plans' => $property->floorPlans->map(fn ($fp) => [
                    'label' => $fp->label, 'size_from' => $fp->size_from, 'size_to' => $fp->size_to,
                    'existing_image' => $fp->image, 'image_url' => $fp->image ? media_url($fp->image) : null,
                ])->values(),
            ],
            'agent_company_id' => $property->agent?->company_id,
            'gallery' => $property->galleryImages(),
            'files' => [
                'brochure' => $property->brochure_path ? media_url($property->brochure_path) : null,
                'floor_plan_file' => $details?->floor_plan_file ? media_url($details->floor_plan_file) : null,
                'floor_plan_file_ext' => $details?->floor_plan_file ? (strtoupper(pathinfo(parse_url($details->floor_plan_file, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)) ?: 'FILE') : null,
                'og_image' => !empty($property->metadata['og_image']) ? media_url($property->metadata['og_image']) : null,
                'permit_qr' => $property->permit_qr ? media_url($property->permit_qr) : null,
            ],
            'permit_status' => match (true) {
                in_array($verifiedVia, ['dld', 'adrec'], true) => ['verified', 'Verification successful', 'Verified with ' . strtoupper($verifiedVia) . ' on ' . $property->permit_verified_at->format('d M Y') . '.'],
                $verifiedVia === 'manual' => ['verified', 'Verified', 'Verified on ' . $property->permit_verified_at->format('d M Y') . '.'],
                filled($property->permit_number) => ['pending', 'Not verified yet', config('permits.superadmin_approval')
                    ? 'Validate the permit, then save — MW Realty approves it before the listing goes live.'
                    : 'Validate the permit, then save — the listing goes live once the permit is verified.'],
                default => ['idle', 'Ready to validate', 'Enter the permit number above.'],
            },
            'permit_verified' => in_array($verifiedVia, ['dld', 'adrec'], true),
            'compliance_status' => $property->compliance_status,
            'compliance_label' => $property->complianceLabel(),
            'can_go_live' => $property->canGoLive(),
            // Why this listing isn't live (permit not verified / expired / taken down): [tone, icon, text].
            'review_alert' => !$property->canGoLive() && !$property->isSold() ? (match (true) {
                $property->awaitingImportReview() => ['info', 'fa-user-shield', 'Imported from Property Finder — MW Realty is reviewing it. After approval, validate the permit in Core details to put it on the website.'],
                $property->awaitingApproval() => ['info', 'fa-user-shield', 'Not on the website yet: waiting for MW Realty to approve this listing. It goes live as soon as it is approved — you\'ll be told by email.'],
                default => match ($property->compliance_status) {
                    Property::COMPLIANCE_CHANGES_REQUESTED => ['danger', 'fa-rotate-left', 'MW Realty took this listing down. Check the permit details, validate the permit again in Core details, then click Update.'],
                    Property::COMPLIANCE_EXPIRED => ['danger', 'fa-ban', 'The permit expired, so the listing is offline. Enter the renewed permit in Core details, validate it and click Update.'],
                    Property::COMPLIANCE_PENDING => ['info', 'fa-shield-halved', 'Not on the website yet: the permit isn\'t verified. Click Validate next to the permit number in Core details, then Update — it goes live as soon as the permit is verified.'],
                    default => ['warning', 'fa-file-circle-exclamation', 'Not on the website yet — still needed in Core details: ' . (implode(', ', $compliance->missingItems($property)) ?: 'the permit details') . '. Then validate the permit and click Update.'],
                },
            }) : null,
        ]]);
    }

    /**
     * Add a listing
     *
     * Multipart (photos, floor plans, brochure, permit QR, OG image). Same fields as the web form —
     * see App\Http\Requests\PropertyRequest. Plan limits are enforced here.
     *
     * @bodyParam segment string residential | commercial. Example: residential
     */
    public function store(PropertyRequest $request)
    {
        $segment = $this->segment($request);

        $listingOwner = null;
        if (!$this->isAdmin()) {
            $owner = PortalUser::lockForUpdate()->findOrFail(Auth::guard('portal')->id());
            // Locked too, so two simultaneous adds can't both take the last slot of the plan.
            $listingOwner = $owner->isAgencyAgent() ? PortalUser::lockForUpdate()->findOrFail($owner->company_id) : $owner;

            if ($owner->status !== 'approved') {
                abort(403, 'Your account needs to be approved by Super Admin before you can add properties.');
            }
            if ($listingOwner->remainingPropertySlots() === 0) {
                abort(422, "You've reached your plan's property limit. Upgrade your plan to add more listings.");
            }
        }

        $data = $request->only([
            'rera_id', 'listing_type', 'completion_status', 'property_type', 'category',
            'location', 'postal_code', 'latitude', 'longitude', 'bedrooms', 'bathrooms', 'sqft', 'price', 'currency',
            ...self::COMPLIANCE_INPUTS,
        ]);
        $data = array_merge($data, $this->listingFields($request));
        $data = app(\App\Services\Permits\PermitVerifier::class)->apply($data, $request->all(), null, $listingOwner);
        if (($data['listing_type'] ?? null) !== 'rent') {
            $data['rental_period'] = null;
        }
        $this->assertPermitRules($data);
        $this->assertNotDuplicate($request, $data, null, $listingOwner?->id);
        // Reuse the read-only form ID if valid and unused, otherwise generate a fresh RERA ID.
        $referenceNo = $request->input('reference_no');
        $data['reference_no'] = is_string($referenceNo) && preg_match('/^RERA\d+$/', $referenceNo) && !Property::where('reference_no', $referenceNo)->exists()
            ? $referenceNo
            : Property::nextReferenceNo();
        // null when Super Admin creates it directly (a house/MW Realty listing with no portal owner).
        // An agency agent's listing belongs to the agency (they stay its agent).
        $data['portal_user_id'] = $listingOwner?->id;
        $data['agent_id'] = $this->resolveAgentId($request, $listingOwner);
        $data['created_by_type'] = $this->isAdmin() ? 'admin' : ($this->viewer()->isAgency() ? 'agency' : 'agent');
        $data['created_by_id'] = $this->isAdmin() ? Auth::guard('cms')->id() : $this->viewer()->id;
        $data += $this->editorStamp();
        $data['translations'] = $request->input('translations', []);
        $data['status'] = $request->boolean('status') && ($this->isAdmin() || $this->viewer()->isApproved());
        // Featuring is booked afterwards from the listing card / Featured menu (dates + plan quota).
        $data['featured'] = false;
        $data['segment'] = $segment;
        $data['published_at'] = $request->input('published_at') ?: null;
        // Column is NOT NULL with a schema default — an explicit null in the insert bypasses that default.
        $data['order_index'] = $request->input('order_index') ?: 0;

        $title = $request->input('translations.' . config('app.fallback_locale', 'en') . '.title')
            ?? collect($request->input('translations', []))->first()['title'] ?? null;
        $data['slug'] = $request->input('slug') ?: Str::slug($title . '-' . Str::random(5));

        $metadata = $request->input('metadata', []);
        if ($request->hasFile('metadata_og_image')) {
            $metadata['og_image'] = app(\App\Services\ManagedFiles::class)->store($request->file('metadata_og_image'), 'properties/metadata');
        }
        $data['metadata'] = $metadata;

        $property = Property::create($data);
        if ($request->hasFile('brochure')) {
            $property->update(['brochure_path' => app(\App\Services\ManagedFiles::class)->store($request->file('brochure'), 'properties/brochures')]);
        }
        $this->recordAgentChange($property, null, $property->agent_id, created: true);

        if ($request->hasFile('images')) {
            $folder = app(\App\Services\PropertyGallery::class)->folderValue('properties/' . $property->reference_no);
            $number = 0;
            $sequence = [];
            $watermark = app(\App\Services\Watermark::class)->activeSettings($property->owner);
            foreach ($request->file('images') as $file) {
                $number++;
                $this->storeGalleryImage($file, $folder, $property->reference_no, $number, $watermark);
                $sequence[] = $number;
            }
            $property->update(['image_path' => $folder, 'image_sequence' => implode(',', $sequence), 'image_next_number' => $number]);
        }

        $detailData = ['property_id' => $property->id, ...$this->detailFields($request)];
        if ($request->hasFile('floor_plan_file')) {
            $detailData['floor_plan_file'] = app(\App\Services\ManagedFiles::class)->store($request->file('floor_plan_file'), 'properties/floor-plans');
        }
        PropertyDetail::create($detailData);

        foreach ($this->processFloorPlans($request) as $row) {
            $property->floorPlans()->create($row);
        }
        $property->nearbyPlaces()->sync($this->allowedNearbyPlaceIds($request));

        $compliance = app(ListingComplianceService::class);
        $compliance->storeUploads($request, $property);
        $compliance->afterSave($property, null);

        return response()->json([
            'success' => true,
            'id' => $property->id,
            'segment' => $property->segment,
            'message' => $this->itemLabel($segment) . ' created successfully.' . $this->complianceToast($property),
        ]);
    }

    /**
     * Update a listing
     *
     * Multipart — send POST with `_method=PUT` when files are included. Fields that must match a
     * verified / approved permit keep their saved values, whatever is posted.
     */
    public function update(PropertyRequest $request, $id)
    {
        $property = $this->findAccessible($id);
        $previousAgentId = $property->agent_id;
        $compliance = app(ListingComplianceService::class);
        $complianceBefore = $compliance->snapshot($property);

        // reference_no is deliberately excluded — it's fixed at creation (read-only in the form)
        // since it's also the gallery folder/filename key; changing it would orphan existing photos.
        $data = $request->only([
            'rera_id', 'listing_type', 'completion_status', 'property_type', 'category',
            'location', 'postal_code', 'latitude', 'longitude', 'bedrooms', 'bathrooms', 'sqft', 'price', 'currency',
            ...self::COMPLIANCE_INPUTS,
        ]);
        $data = array_merge($data, $this->listingFields($request));
        $data = app(\App\Services\Permits\PermitVerifier::class)->apply($data, $request->all(), $property, $property->owner);
        foreach ($this->form->lockedFields($property) as $field) {
            $data[$field] = $property->{$field};
        }
        if (($data['listing_type'] ?? null) !== 'rent') {
            $data['rental_period'] = null;
        }
        $this->assertPermitRules($data);
        $this->assertNotDuplicate($request, $data, $property, $property->portal_user_id);
        $data['translations'] = $request->input('translations', []);
        $data['status'] = $request->boolean('status');
        $data['agent_id'] = $this->resolveAgentId($request, $property->owner);
        $data['published_at'] = $request->input('published_at') ?: null;
        $data['order_index'] = $request->input('order_index') ?: 0;
        // `featured` is deliberately not touched here: the form has no Featured switch (featuring
        // is booked with dates from the listing card / Featured menu).
        if ($request->filled('slug')) {
            $data['slug'] = $request->input('slug');
        }

        $metadata = $request->input('metadata', []);
        $existingMetadata = $property->metadata ?? [];
        if ($request->hasFile('metadata_og_image')) {
            if (!empty($existingMetadata['og_image'])) {
                app(\App\Services\ManagedFiles::class)->delete($existingMetadata['og_image']);
            }
            $metadata['og_image'] = app(\App\Services\ManagedFiles::class)->store($request->file('metadata_og_image'), 'properties/metadata');
        } else {
            $metadata['og_image'] = $existingMetadata['og_image'] ?? null;
        }
        $data['metadata'] = $metadata;
        $data += $this->editorStamp();

        $property->update($data);
        if ($request->hasFile('brochure')) {
            if ($property->brochure_path) {
                app(\App\Services\ManagedFiles::class)->delete($property->brochure_path);
            }
            $property->update(['brochure_path' => app(\App\Services\ManagedFiles::class)->store($request->file('brochure'), 'properties/brochures')]);
        }
        $this->recordAgentChange($property, $previousAgentId, $property->agent_id);

        if ($request->hasFile('images')) {
            // Backfills a reference_no for any property that predates the gallery naming.
            if (!$property->reference_no) {
                $property->update(['reference_no' => Property::nextReferenceNo()]);
            }
            $folder = app(\App\Services\PropertyGallery::class)->folderValue($property->image_path ?: ('properties/' . $property->reference_no));
            $number = $property->image_next_number;
            $sequence = $property->galleryNumbers();
            $watermark = app(\App\Services\Watermark::class)->activeSettings($property->owner);
            foreach ($request->file('images') as $file) {
                $number++;
                $this->storeGalleryImage($file, $folder, $property->reference_no, $number, $watermark);
                $sequence[] = $number;
            }
            $property->update(['image_path' => $folder, 'image_sequence' => implode(',', $sequence), 'image_next_number' => $number]);
        }

        $detailData = $this->detailFields($request);
        $existingDetail = $property->details;
        if ($request->hasFile('floor_plan_file')) {
            if ($existingDetail?->floor_plan_file) {
                app(\App\Services\ManagedFiles::class)->delete($existingDetail->floor_plan_file);
            }
            $detailData['floor_plan_file'] = app(\App\Services\ManagedFiles::class)->store($request->file('floor_plan_file'), 'properties/floor-plans');
        }
        PropertyDetail::updateOrCreate(['property_id' => $property->id], $detailData);

        $property->floorPlans()->delete();
        foreach ($this->processFloorPlans($request) as $row) {
            $property->floorPlans()->create($row);
        }
        $property->nearbyPlaces()->sync($this->allowedNearbyPlaceIds($request));

        $compliance->storeUploads($request, $property);
        $compliance->afterSave($property, $complianceBefore);

        return response()->json([
            'success' => true,
            'id' => $property->id,
            'segment' => $property->segment,
            'message' => $this->itemLabel($property->segment) . ' updated successfully.' . $this->complianceToast($property),
        ]);
    }

    /**
     * Delete a listing
     *
     * With all its photos and files.
     */
    public function destroy($id)
    {
        $this->deletePropertyWithFiles($this->findOwned($id));

        return response()->json(['success' => true]);
    }

    /**
     * Bulk action
     *
     * Mark Active / Mark Inactive / Delete Selected. Only listings that passed the permit review
     * (and whose permit is still valid) can be switched on; sold / rented ones are skipped.
     *
     * @bodyParam action string required active | inactive | delete. Example: inactive
     * @bodyParam ids integer[] required Example: [12, 13]
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:active,inactive,delete',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $query = Property::query()
            ->when($this->ownerId(), fn ($q, $ownerId) => $q->where('portal_user_id', $ownerId))
            ->whereIn('id', $request->input('ids'));

        if ($request->input('action') === 'delete') {
            $properties = $query->with(['details', 'images', 'floorPlans'])->get();
            foreach ($properties as $property) {
                $this->deletePropertyWithFiles($property);
            }

            return response()->json(['success' => true, 'affected' => $properties->count()]);
        }

        $activate = $request->input('action') === 'active';
        $affected = $query->available()->when($activate, fn ($q) => $q->compliant())->update(['status' => $activate]);

        return response()->json(['success' => true, 'affected' => $affected]);
    }

    /**
     * Reorder a page
     *
     * Drag-and-drop on the card grid. `order` is the new id sequence of the current page only, so the
     * whole owner's list is renumbered with that page slice swapped in.
     *
     * @bodyParam segment string residential | commercial. Example: residential
     * @bodyParam order integer[] required Example: [14, 12, 13]
     */
    public function reorder(Request $request)
    {
        $request->validate(['order' => 'required|array|min:1', 'order.*' => 'integer']);
        $allIds = $this->orderedListings($this->segment($request), strict: true)->pluck('id');

        $pageIds = collect($request->input('order'))->map(fn ($id) => (int) $id)->filter(fn ($id) => $allIds->contains($id))->values();
        if ($pageIds->isEmpty()) {
            return response()->json(['success' => true]);
        }

        // Slot the reordered page back in where that page's items currently start.
        $start = $allIds->search(fn ($id) => $pageIds->contains($id));
        $rest = $allIds->reject(fn ($id) => $pageIds->contains($id))->values();
        $this->saveOrder($rest->slice(0, $start)->concat($pageIds)->concat($rest->slice($start))->values());

        return response()->json(['success' => true]);
    }

    /**
     * Move to a position
     *
     * "Move to top / bottom / position #N" — works across pages. `position` is 1-based over the
     * listing's whole menu. Returns the page the listing now sits on.
     *
     * @bodyParam position string required top | bottom | a number. Example: 3
     * @response 200 {"success": true, "position": 3, "page": 1}
     */
    public function move(Request $request, $id)
    {
        $request->validate(['position' => 'required']);
        $property = $this->findOwned($id);

        $allIds = $this->orderedListings($property->segment, strict: true)->pluck('id');
        abort_unless($allIds->contains($property->id), 404);

        $position = match ($request->input('position')) {
            'top' => 1,
            'bottom' => $allIds->count(),
            default => (int) $request->input('position'),
        };
        if ($position < 1 || $position > $allIds->count()) {
            throw ValidationException::withMessages(['position' => 'Choose a position between 1 and ' . $allIds->count() . '.']);
        }

        $rest = $allIds->reject(fn ($listingId) => $listingId === $property->id)->values();
        $rest->splice($position - 1, 0, [$property->id]);
        $this->saveOrder($rest);

        return response()->json(['success' => true, 'position' => $position, 'page' => (int) ceil($position / self::PER_PAGE)]);
    }

    /**
     * Switch a listing on / off
     *
     * 422 when it's sold / rented, or can't go live yet (permit not verified / not approved).
     */
    public function toggleStatus($id)
    {
        $property = $this->findOwned($id);
        abort_unless($this->isAdmin() || $this->viewer()->isApproved(), 403);
        if ($property->isSold()) {
            return response()->json(['message' => 'This listing is marked ' . $property->sold_type . '. Revert it to available first (Sold Listings).'], 422);
        }
        if (!$property->status && !$property->canGoLive()) {
            return response()->json(['message' => "This listing can't go live yet (" . strtolower($property->complianceLabel()) . ').' . ($property->awaitingApproval()
                ? ' MW Realty has to approve it first.'
                : ' Its advertising permit has to be verified first — open the listing and use Validate in Core details.')], 422);
        }
        $property->status = !$property->status;
        $property->save();

        return response()->json(['success' => true, 'status' => $property->status]);
    }

    /* ----------------------------------------------------------------- saving helpers */

    /** Per-emirate permit rules the form can't express on its own (PermitRules). */
    private function assertPermitRules(array $data): void
    {
        $errors = [];
        if (\App\Support\PermitRules::rentOnly($data['permit_type'] ?? null) && ($data['listing_type'] ?? null) !== 'rent') {
            $errors['listing_type'] = 'A DTCM permit is for holiday homes, which can only be listed for rent.';
        }
        if (($data['emirate'] ?? null) === \App\Support\PermitRules::NORTHERN && !isset(\App\Support\PermitRules::NORTHERN_CITIES[$data['permit_city'] ?? ''])) {
            $errors['permit_city'] = 'Choose the city.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Blocks entering the same listing twice: one permit = one listing, and the same unit can't be
     * listed twice for the same purpose by the same account while the first one is still on the books.
     */
    private function assertNotDuplicate(Request $request, array $data, ?Property $property, ?int $ownerId): void
    {
        $verifier = app(\App\Services\Permits\PermitVerifier::class);
        if (!empty($data['permit_number']) && ($taken = $verifier->listingUsing($data['permit_number'], $property?->id))) {
            throw ValidationException::withMessages([
                'permit_number' => "This permit is already used by listing {$taken->reference_no}. Each permit covers one listing only.",
            ]);
        }

        $unit = trim((string) $request->input('unit_number'));
        if ($unit === '' || empty($data['listing_type'])) {
            return;
        }
        $duplicate = Property::query()
            ->when($ownerId, fn ($q) => $q->where('portal_user_id', $ownerId), fn ($q) => $q->whereNull('portal_user_id'))
            ->when($property, fn ($q) => $q->whereKeyNot($property->id))
            ->where('listing_type', $data['listing_type'])
            ->where('location', $data['location'] ?? null)
            ->whereNull('sold_at')
            ->whereHas('details', fn ($q) => $q->whereRaw('LOWER(TRIM(unit_number)) = ?', [mb_strtolower($unit)]))
            ->first(['id', 'reference_no']);
        if ($duplicate) {
            throw ValidationException::withMessages([
                'unit_number' => "Unit {$unit} in this location is already listed for " . ($data['listing_type'] === 'rent' ? 'rent' : 'sale') . " as {$duplicate->reference_no}. Edit that listing instead of adding it again.",
            ]);
        }
    }

    /** properties columns: emirate, rental period, available dates. */
    private function listingFields(Request $request): array
    {
        $dates = $request->input('availability') === 'from_date'
            ? collect((array) $request->input('available_dates', []))->filter()->unique()->sort()->values()->all()
            : [];

        return [
            'emirate' => $request->input('emirate') ?: null,
            // A rental period only means something on a rent listing.
            'rental_period' => $request->input('listing_type') === 'rent' ? ($request->input('rental_period') ?: null) : null,
            'available_dates' => $dates ?: null,
        ];
    }

    /** property_details columns from the form. */
    private function detailFields(Request $request): array
    {
        return [
            'amenities' => $this->processOptionList($request, 'amenity'),
            'easy_access' => $this->processOptionList($request, 'easy_access'),
            'property_attributes' => $this->processOptionList($request, 'property_attribute'),
            'developer' => $request->input('developer'),
            'unit_number' => $request->input('unit_number'),
            'owner_name' => $request->input('owner_name'),
            'upgraded' => $request->boolean('upgraded'),
            'video_tour_url' => $request->input('video_tour_url'),
            'cheques' => $request->input('listing_type') === 'rent' ? $request->input('cheques') : null,
            'year_built' => $request->input('year_built'),
            'floor' => $request->input('floor'),
            'parking' => $request->input('parking'),
            'garage' => $request->input('garage'),
            ...$this->furnishingFields($request),
            'direct_from_owner' => $request->input('direct_from_owner'),
            'security_deposit' => $request->input('security_deposit'),
            'virtual_tour_url' => $request->input('virtual_tour_url'),
            'view' => $request->input('view'),
        ];
    }

    /**
     * Amenities / Easy Access / Attributes: the options ticked on the form (values from Master ›
     * Property Options), stored as {key, icon, label} rows in the option list's order. The label and
     * icon are a copy for older readers; pages resolve the live option by key (PropertyPageService).
     */
    private function processOptionList(Request $request, string $filterKey): array
    {
        $picked = array_map('strval', (array) $request->input(Filter::ICON_LISTS[$filterKey], []));
        if (!$picked) {
            return [];
        }

        return FilterValue::whereHas('filter', fn ($q) => $q->where('key', $filterKey))
            ->whereIn('value', $picked)->orderBy('order_index')->orderBy('id')->get()
            ->map(fn ($o) => [
                'key' => $o->value,
                'icon' => $o->icon,
                'label' => collect($o->translations)->map(fn ($t) => $t['label'] ?? '')->filter()->all(),
            ])->values()->all();
    }

    /**
     * `furnished` holds a furnishing option value (Master › Property Options). An unknown value is
     * dropped; an older client still posting 1 / 0 maps to furnished / unfurnished.
     */
    private function furnishingFields(Request $request): array
    {
        $value = (string) $request->input('furnished', '');
        if (in_array($value, ['0', '1'], true)) {
            $value = $value === '1' ? 'furnished' : PropertyDetail::UNFURNISHED;
        }
        if ($value !== '' && !FilterValue::whereHas('filter', fn ($q) => $q->where('key', Filter::FURNISHING_KEY))->where('value', $value)->exists()) {
            $value = '';
        }

        return ['furnished' => $value !== '' ? $value : null];
    }

    /**
     * Which agent_id to actually save. Agent selection is always optional for an agency, but when
     * one is given it is validated here, never trusted from the form:
     * - An Agent login is always the listing's agent (own listing, or an agency listing assigned to them).
     * - An agency listing (Company login, or Super Admin editing one) only accepts an active,
     *   approved agent of that same agency.
     * - An independent agent's listing is always that agent's.
     * - A house listing (Super Admin, no owner) accepts any active, approved agent.
     */
    private function resolveAgentId(Request $request, ?PortalUser $listingOwner): ?int
    {
        $user = $this->viewer();
        if ($user && $user->isAgent()) {
            return $user->id;
        }

        $requested = $request->filled('agent_id') ? (int) $request->input('agent_id') : null;
        $owner = $user ?? $listingOwner;
        if ($owner && $owner->isAgent()) {
            return $owner->id;
        }
        if (!$requested) {
            return null;
        }

        $valid = $owner
            ? $owner->hasEligibleAgent($requested)
            : PortalUser::whereKey($requested)->where('type', 'agent')->approved()->where('is_active', true)->exists();
        if (!$valid) {
            throw ValidationException::withMessages(['agent_id' => 'Choose an active, approved agent from this agency.']);
        }

        return $requested;
    }

    /** Property-history row for an agent change (or the initial assignment on create). */
    private function recordAgentChange(Property $property, ?int $fromAgent, ?int $toAgent, bool $created = false): void
    {
        if (!$created && $fromAgent === $toAgent) {
            return;
        }
        $action = match (true) {
            $created => 'created',
            $fromAgent === null => 'agent_assigned',
            $toAgent === null => 'agent_unassigned',
            default => 'agent_changed',
        };
        $agencyId = $property->owner?->isAgency() ? $property->portal_user_id : null;

        app(\App\Services\Agency\AgencyMembershipService::class)->recordPropertyChange(
            $property, $action, $created ? null : $agencyId, $agencyId, $fromAgent, $toAgent,
            \App\Services\Agency\AssignmentActor::current(),
        );
    }

    /**
     * Every gallery photo is re-encoded to JPEG and named `{reference_no}-{n}.jpeg` regardless of
     * what was uploaded — so reordering only ever has to rewrite the `image_sequence` list.
     * $watermark: the listing owner's active watermark settings (App\Services\Watermark), stamped in.
     */
    private function storeGalleryImage(UploadedFile $file, string $folder, string $referenceNo, int $number, ?array $watermark = null): void
    {
        app(\App\Services\PropertyGallery::class)->put(
            $folder,
            $referenceNo . '-' . $number . '.jpeg',
            \App\Services\PropertyGallery::jpeg($file->getRealPath(), $watermark)
        );
    }

    /** Submitted nearby place ids that still exist — every place is usable by everyone, whoever added it. */
    private function allowedNearbyPlaceIds(Request $request): array
    {
        $ids = array_map('intval', (array) $request->input('nearby_places', []));

        return $ids ? \App\Models\NearbyPlace::whereIn('id', $ids)->pluck('id')->all() : [];
    }

    /**
     * Each floor plan row carries its own image (uploaded fresh, or kept via the row's
     * `existing_image` field when editing). Rows without a title are skipped.
     */
    private function processFloorPlans(Request $request): array
    {
        $rows = (array) $request->input('floor_plans', []);
        $files = (array) $request->file('floor_plans', []);
        $result = [];

        foreach ($rows as $i => $row) {
            $label = trim($row['label'] ?? '');
            if ($label === '') {
                continue;
            }
            $image = $row['existing_image'] ?? null;
            if (isset($files[$i]['image']) && $files[$i]['image'] instanceof UploadedFile) {
                $image = app(\App\Services\ManagedFiles::class)->store($files[$i]['image'], 'properties/floor-plans');
            }
            $result[] = [
                'label' => $label,
                'image' => $image ?: null,
                'size_from' => ($row['size_from'] ?? null) ?: null,
                'size_to' => ($row['size_to'] ?? null) ?: null,
                'order_index' => count($result) + 1,
            ];
        }

        return $result;
    }

    /** Appended to the save message: why the listing isn't on the website yet. */
    private function complianceToast(Property $property): string
    {
        if ($property->awaitingApproval()) {
            return ' It was sent to MW Realty for approval and goes live once approved.';
        }

        return match ($property->compliance_status) {
            Property::COMPLIANCE_PENDING => ' It stays off the website until its permit is verified — click Validate next to the permit number in Core details.',
            Property::COMPLIANCE_DRAFT => ' It stays off the website until the permit details are added and validated in Core details.',
            Property::COMPLIANCE_EXPIRED => ' The permit has expired, so it stays off the website — enter and validate the renewed permit.',
            default => '',
        };
    }

    /** Renumbers order_index 1..n in the given id order (only rows whose value actually changes). */
    private function saveOrder(\Illuminate\Support\Collection $orderedIds): void
    {
        $current = Property::whereIn('id', $orderedIds)->pluck('order_index', 'id');

        DB::transaction(function () use ($orderedIds, $current) {
            foreach ($orderedIds->values() as $index => $id) {
                if ((int) ($current[$id] ?? -1) !== $index + 1) {
                    Property::whereKey($id)->update(['order_index' => $index + 1]);
                }
            }
        });
    }

    private function deletePropertyWithFiles(Property $property): void
    {
        $files = app(\App\Services\ManagedFiles::class);

        if ($property->image) {
            $files->delete($property->image);
        }
        if ($property->image_path) {
            app(\App\Services\PropertyGallery::class)->deleteAll($property->image_path);
        }
        foreach ($property->images as $image) {
            $files->delete($image->image);
        }
        foreach ($property->floorPlans as $floorPlan) {
            if ($floorPlan->image) {
                $files->delete($floorPlan->image);
            }
        }
        if (!empty($property->metadata['og_image'])) {
            $files->delete($property->metadata['og_image']);
        }
        if ($detail = $property->details) {
            foreach (['floor_plan_image', 'floor_plan_file'] as $field) {
                if ($detail->{$field}) {
                    $files->delete($detail->{$field});
                }
            }
            foreach (['amenities', 'easy_access', 'property_attributes'] as $field) {
                foreach ($detail->{$field} ?? [] as $row) {
                    // Rows picked from Master › Property Options (they carry a `key`) share the option's
                    // icon with every other listing — only an old per-listing upload belongs to this one.
                    if (!empty($row['icon']) && empty($row['key']) && str_starts_with($row['icon'], 'properties/icons')) {
                        $files->delete($row['icon']);
                    }
                }
            }
        }

        $property->delete();
    }

    /** Who is saving the listing — updated_by_* (shown as "Updated by" in Listing Performance). */
    private function editorStamp(): array
    {
        return [
            'updated_by_type' => $this->isAdmin() ? 'admin' : ($this->viewer()->isAgency() ? 'agency' : 'agent'),
            'updated_by_id' => $this->isAdmin() ? Auth::guard('cms')->id() : $this->viewer()->id,
        ];
    }
}
