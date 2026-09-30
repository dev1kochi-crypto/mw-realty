<?php

namespace App\Http\Controllers\Portal;

use App\Models\Property;
use App\Models\PropertyDetail;
use App\Models\Filter;
use App\Models\CmsKit\Language;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Shared Properties dashboard: Super Admin (cms guard) sees every listing;
 * an Agent/Company (portal guard) sees and can only touch their own. Which
 * behaviour applies is decided entirely by ownerId() — null means "no scope".
 */
class PortalPropertyController extends Controller
{
    /**
     * A portal-guard login always wins, even if a superadmin cms-guard session is
     * also active in the same browser (e.g. testing the portal in a second tab) —
     * otherwise there'd be no way to see your own scoped view without logging out
     * of /admin first.
     */
    protected function isAdmin(): bool
    {
        return !Auth::guard('portal')->check() && (bool) Auth::guard('cms')->user()?->hasRole('superadmin');
    }

    /**
     * The portal_user_id to scope queries to, or null for a Super Admin (global view).
     */
    protected function ownerId(): ?int
    {
        return Auth::guard('portal')->check() ? Auth::guard('portal')->user()->id : null;
    }

    /** Listings per page on the card grid — a multiple of the 4-column row so pages end on a full row. */
    protected const PER_PAGE = 16;

    /**
     * Which CRM menu this controller serves. Commercial is the same table, form and screens,
     * split only by `segment` (see PortalCommercialController). Plan property limits count both.
     */
    protected function segment(): string
    {
        return Property::SEGMENT_RESIDENTIAL;
    }

    /** Route-name prefix for this menu's own pages (index/create/store/edit/update/show/reorder/move). */
    protected function routePrefix(): string
    {
        return $this->segment() === Property::SEGMENT_COMMERCIAL ? 'portal.commercial' : 'portal.properties';
    }

    /** Labels + route prefix the shared portal.properties.* views use to render either menu. */
    protected function sectionData(): array
    {
        $commercial = $this->segment() === Property::SEGMENT_COMMERCIAL;

        return [
            'routePrefix' => $this->routePrefix(),
            'segment' => $this->segment(),
            'sectionTitle' => $commercial ? 'Commercial' : 'Properties',
            'itemLabel' => $commercial ? 'Commercial Property' : 'Property',
        ];
    }

    protected function viewer(): ?\App\Models\PortalUser
    {
        return Auth::guard('portal')->user();
    }

    /**
     * This menu's listings in display order: every owner's for Super Admin; otherwise the viewer's
     * own — plus, unless $strict, the agency listings assigned to an agency agent. Reordering is
     * always $strict (an agent never renumbers the agency's list).
     */
    protected function orderedListings(bool $strict = false)
    {
        return Property::query()
            ->segment($this->segment())
            // Sold / rented listings move to the Sold Listings menu.
            ->available()
            ->when($this->ownerId(), fn ($q, $ownerId) => $strict
                ? $q->where('portal_user_id', $ownerId)
                : $q->accessibleBy($this->viewer()))
            ->orderBy('order_index')
            ->latest()
            // Tie-breaker: without it rows sharing order_index/created_at come back in an arbitrary
            // order per query, so a listing can repeat on one page and be missing from the next.
            ->orderByDesc('id');
    }

    /** A listing opened through the other menu's URL (e.g. a dashboard link) goes to its own menu. */
    protected function redirectToOwnMenu(Property $property, string $action)
    {
        if ($property->segment === $this->segment()) {
            return null;
        }
        $prefix = $property->segment === Property::SEGMENT_COMMERCIAL ? 'portal.commercial' : 'portal.properties';

        return redirect()->route("{$prefix}.{$action}", $property->id);
    }

    /** Free-text search on the listing page: title / address / community / city (any language), ref no, RERA, and owner for Super Admin. */
    protected function applySearch($query, string $search): void
    {
        $query->portalSearch($search, $this->isAdmin());
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
    protected function resolveAgentId(Request $request, ?\App\Models\PortalUser $listingOwner): ?int
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
            : \App\Models\PortalUser::whereKey($requested)->where('type', 'agent')->approved()->where('is_active', true)->exists();

        if (!$valid) {
            throw \Illuminate\Validation\ValidationException::withMessages(['agent_id' => 'Choose an active, approved agent from this agency.']);
        }

        return $requested;
    }

    /** Property-history row for an agent change (or the initial assignment on create). */
    protected function recordAgentChange(Property $property, ?int $fromAgent, ?int $toAgent, bool $created = false): void
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
     * Agent/agency data for the create/edit form: a locked (read-only) agent and/or
     * agency for an Agent or Company login, or a full picker list for Super Admin.
     */
    protected function agentAssignmentOptions(): array
    {
        $user = Auth::guard('portal')->user();

        if ($user && $user->type === 'agent') {
            return ['lockedAgent' => $user, 'lockedAgency' => $user->company, 'agentOptions' => collect(), 'agencyOptions' => collect()];
        }

        if ($user && $user->type === 'company') {
            // Only active, approved agents of this agency can be picked; "no agent" is always allowed.
            return ['lockedAgent' => null, 'lockedAgency' => $user, 'agentOptions' => $user->eligibleAgentsQuery()->reorder('portal_users.name')->get(), 'agencyOptions' => collect()];
        }

        return [
            'lockedAgent' => null,
            'lockedAgency' => null,
            'agentOptions' => \App\Models\PortalUser::where('type', 'agent')->with('company')->orderBy('name')->get(),
            'agencyOptions' => \App\Models\PortalUser::where('type', 'company')->orderBy('name')->get(),
        ];
    }

    /**
     * `furnished` holds a furnishing option value (CRM › Master › Property Options). An unknown value
     * is dropped; an older client still posting 1 / 0 maps to furnished / unfurnished.
     */
    protected function furnishingFields(Request $request): array
    {
        $value = (string) $request->input('furnished', '');
        if (in_array($value, ['0', '1'], true)) {
            $value = $value === '1' ? 'furnished' : \App\Models\PropertyDetail::UNFURNISHED;
        }
        if ($value !== '' && !\App\Models\FilterValue::whereHas('filter', fn ($q) => $q->where('key', Filter::FURNISHING_KEY))->where('value', $value)->exists()) {
            $value = '';
        }

        return ['furnished' => $value !== '' ? $value : null];
    }

    protected function selectFilterOptions(): \Illuminate\Support\Collection
    {
        return Filter::whereIn('key', ['listing_type', 'completion_status', 'property_type', 'location', Filter::FURNISHING_KEY])
            ->where('type', 'select')
            ->with(['activeValues'])
            ->get()
            ->keyBy('key');
    }

    /**
     * PROP001, PROP002, ... — the next unused number after the highest currently on record.
     * Doubles as the gallery folder/filename key (see storeGalleryImage()), so it's generated once
     * up front and never changes afterwards.
     */
    protected function generateReferenceNo(): string
    {
        $maxNumber = Property::where('reference_no', 'like', 'PROP%')
            ->pluck('reference_no')
            ->map(fn ($ref) => (int) substr($ref, 4))
            ->max() ?? 0;

        $number = $maxNumber + 1;
        while (Property::where('reference_no', 'PROP' . str_pad((string) $number, 3, '0', STR_PAD_LEFT))->exists()) {
            $number++;
        }

        return 'PROP' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Every gallery photo is re-encoded to JPEG and named `{reference_no}-{n}.jpeg` regardless of
     * what was uploaded — so reordering (see reorderImages()) only ever has to rewrite the
     * `image_sequence` list, never rename a file.
     */
    protected function storeGalleryImage(UploadedFile $file, string $folder, string $referenceNo, int $number): void
    {
        app(\App\Services\PropertyGallery::class)->put(
            $folder,
            $referenceNo . '-' . $number . '.jpeg',
            $this->convertToJpeg($file->getRealPath())
        );
    }

    protected function convertToJpeg(string $path): string
    {
        $mime = @getimagesize($path)['mime'] ?? null;
        $source = match ($mime) {
            'image/png' => @imagecreatefrompng($path),
            'image/gif' => @imagecreatefromgif($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => @imagecreatefromjpeg($path),
        };
        if (!$source) {
            $source = @imagecreatefromstring(file_get_contents($path));
        }
        if (!$source) {
            throw new \RuntimeException('Could not read the uploaded image.');
        }

        // Flatten onto a white background — PNG/GIF transparency has no equivalent in JPEG.
        $width = imagesx($source);
        $height = imagesy($source);
        $flattened = imagecreatetruecolor($width, $height);
        imagefill($flattened, 0, 0, imagecolorallocate($flattened, 255, 255, 255));
        imagecopy($flattened, $source, 0, 0, 0, 0, $width, $height);
        imagedestroy($source);

        ob_start();
        imagejpeg($flattened, null, 85);
        $binary = ob_get_clean();
        imagedestroy($flattened);

        return $binary;
    }

    /** Feeds the Nearby Places tab's Type dropdown — the Place dropdown loads via AJAX once a Type is picked. */
    /** Submitted nearby place ids, limited to shared places + the current user's own (no tagging someone else's). */
    protected function allowedNearbyPlaceIds(Request $request): array
    {
        $ids = array_map('intval', (array) $request->input('nearby_places', []));
        if (empty($ids)) {
            return [];
        }

        return \App\Models\NearbyPlace::visibleTo($this->ownerId())->whereIn('id', $ids)->pluck('id')->all();
    }

    protected function nearbyPlaceTypes()
    {
        return \App\Models\Filter::where('key', \App\Models\NearbyPlace::FILTER_KEY)->with('activeValues')->first();
    }

    /**
     * Builds one icon-repeater column (amenities / easy_access / property_attributes) from the
     * request: uploads a new icon per row when provided, otherwise keeps whatever the row's
     * `existing_icon` hidden field says (set by the form when editing) — a row with neither a new
     * file nor an existing path just has no icon. Label is per-language (one text input per active
     * language in the form); a row is dropped entirely only if every language's label is empty.
     */
    protected function processIconRepeater(Request $request, string $field): array
    {
        $rows = (array) $request->input($field, []);
        $files = (array) $request->file($field, []);
        $result = [];

        foreach ($rows as $i => $row) {
            $label = collect($row['label'] ?? [])
                ->map(fn ($text) => trim((string) $text))
                ->filter(fn ($text) => $text !== '')
                ->all();
            if (empty($label)) {
                continue;
            }

            $icon = $row['existing_icon'] ?? null;
            if (isset($files[$i]['icon']) && $files[$i]['icon'] instanceof UploadedFile) {
                $icon = app(\App\Services\ManagedFiles::class)->store($files[$i]['icon'], 'properties/icons');
            }

            $result[] = ['icon' => $icon, 'label' => $label];
        }

        return $result;
    }

    /**
     * Each floor plan row carries its own image (uploaded fresh, or kept via the row's
     * `existing_image` hidden field when editing) — there is no single shared diagram anymore.
     */
    protected function processFloorPlans(Request $request): array
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
                'image' => $image,
                'size_from' => $row['size_from'] ?? null ?: null,
                'size_to' => $row['size_to'] ?? null ?: null,
                'order_index' => count($result) + 1,
            ];
        }

        return $result;
    }

    /** Agent filter picker: the agency's agents (all agents for Super Admin) — searched on the server, 20 a page. */
    public function agentOptions(Request $request)
    {
        abort_unless($this->isAdmin() || $this->viewer()?->isAgency(), 403);
        $term = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        $page = ($this->isAdmin()
                ? \App\Models\PortalUser::where('type', 'agent')
                : \App\Models\PortalUser::where('type', 'agent')->whereIn('id', \App\Models\AgencyAgent::where('agency_id', $this->viewer()->id)
                    ->whereIn('status', \App\Models\AgencyAgent::MEMBER_STATUSES)->select('agent_id')))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
            ->orderBy('name')
            ->paginate(20, ['id', 'name']);

        return response()->json([
            'results' => collect($page->items())->map(fn ($agent) => ['id' => $agent->id, 'text' => $agent->name]),
            'pagination' => ['more' => $page->hasMorePages()],
        ]);
    }

    public function index(Request $request, \App\Services\FeaturedListingService $featured)
    {
        // Also applied by the scheduled properties:expire-featured command; running it here keeps
        // this page correct even where the scheduler isn't running (e.g. local dev).
        $featured->sync();

        $search = trim((string) $request->input('q', ''));
        $search = mb_substr($search, 0, 100);

        $query = $this->orderedListings()->with(['owner', 'agent:id,name,avatar']);
        if ($search !== '') {
            $this->applySearch($query, $search);
        }

        // Filters: agent ("none" = agency's own, no agent), buy / rent, active / inactive, premium.
        $filters = [
            'agent' => $request->input('agent') === 'none' ? 'none' : ($request->integer('agent') ?: null),
            'listing' => in_array($request->input('listing'), ['sale', 'rent'], true) ? $request->input('listing') : null,
            'status' => in_array($request->input('status'), ['active', 'inactive'], true) ? $request->input('status') : null,
            'premium' => $request->boolean('premium'),
        ];
        $query->when($filters['agent'] === 'none', fn ($q) => $q->whereNull('properties.agent_id'))
            ->when(is_int($filters['agent']), fn ($q) => $q->where('properties.agent_id', $filters['agent']))
            ->when($filters['listing'], fn ($q, $type) => $q->where('properties.listing_type', $type))
            ->when($filters['status'], fn ($q, $status) => $q->where('properties.status', $status === 'active'))
            ->when($filters['premium'], fn ($q) => $q->where('properties.featured', true));
        $filtered = (bool) array_filter($filters);
        $filterAgent = is_int($filters['agent']) ? \App\Models\PortalUser::find($filters['agent'], ['id', 'name']) : null;

        $properties = $query->paginate(self::PER_PAGE)->withQueryString();

        $planUsage = null;
        if (!$this->isAdmin()) {
            $owner = $this->viewer()->listingOwner();
            $planUsage = [
                'plan' => $owner->plan,
                'used' => $owner->properties()->count(),
                'remaining' => $owner->remainingPropertySlots(),
            ];
        }

        return view('portal.properties.index', array_merge([
            'properties' => $properties,
            'search' => $search,
            'filters' => $filters,
            'filtered' => $filtered,
            'filterAgent' => $filterAgent,
            // Agent filter only makes sense for an agency (its agents) or Super Admin (everyone's).
            'canFilterAgent' => $this->isAdmin() || $this->viewer()?->isAgency(),
            // Listings on this page whose feature dates the viewer may change.
            'featureEditable' => $featured->editableIds(
                $this->isAdmin() ? null : Auth::guard('portal')->user(),
                collect($properties->items())->filter(fn ($p) => $p->featured || $p->isFeatureScheduled())->pluck('id'),
            ),
            // Whole list size (unfiltered), for the "Move to position" picker.
            'totalListings' => $this->orderedListings()->count(),
            'isAdmin' => $this->isAdmin(),
            'planUsage' => $planUsage,
            'featuredQuota' => $this->isAdmin() ? null : $featured->quota(Auth::guard('portal')->user()),
        ], $this->sectionData()));
    }

    /**
     * Feature popup (listing cards and the Featured menu): book a start + end date. Agents/companies
     * spend their plan's quota within its max duration; a Super Admin has no limits and may leave
     * the end date open.
     */
    public function feature(Request $request, \App\Services\FeaturedListingService $featured, $id)
    {
        $property = $this->findOwned($id);
        $request->validate([
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => ($this->isAdmin() ? 'nullable' : 'required') . '|date_format:Y-m-d',
        ]);

        if ($this->isAdmin()) {
            $featured->featureAsAdmin($property, $request->input('start_date'), $request->input('end_date'));
        } else {
            abort_unless(Auth::guard('portal')->user()->isApproved(), 403);
            $featured->feature(Auth::guard('portal')->user(), $property, $request->input('start_date'), $request->input('end_date'));
        }

        return response()->json(['success' => true]);
    }

    /** "Edit dates" on a live or scheduled feature (Featured menu / listing card). */
    public function updateFeature(Request $request, \App\Services\FeaturedListingService $featured, $id)
    {
        $property = $this->findOwned($id);
        $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => ($this->isAdmin() ? 'nullable' : 'required') . '|date_format:Y-m-d',
        ]);
        if (!$this->isAdmin()) {
            abort_unless(Auth::guard('portal')->user()->isApproved(), 403);
        }

        $featured->reschedule(
            $property,
            $this->isAdmin() ? null : Auth::guard('portal')->user(),
            $request->input('start_date'),
            $request->input('end_date'),
        );

        return response()->json(['success' => true]);
    }

    public function unfeature(\App\Services\FeaturedListingService $featured, $id)
    {
        $featured->stop($this->findOwned($id));

        return response()->json(['success' => true]);
    }

    public function create()
    {
        if (!$this->isAdmin() && Auth::guard('portal')->user()->status !== 'approved') {
            return redirect()->route('portal.dashboard')
                ->with('error', 'Your account needs to be approved by Super Admin before you can add properties. Complete your profile while you wait for review.');
        }

        $languages = Language::where('status', true)->get();
        $filterOptions = $this->selectFilterOptions();
        // An agency agent lists on behalf of their agency, so the agency's plan limit applies.
        $remainingSlots = $this->isAdmin() ? null : $this->viewer()->listingOwner()->remainingPropertySlots();

        if ($remainingSlots === 0) {
            return redirect()->route($this->routePrefix() . '.index')
                ->with('error', "You've reached your plan's property limit. Upgrade your plan to add more listings.");
        }

        return view('portal.properties.create', array_merge([
            'languages' => $languages,
            'filterOptions' => $filterOptions,
            'isAdmin' => $this->isAdmin(),
            'remainingSlots' => $remainingSlots,
            'nearbyPlaceTypes' => $this->nearbyPlaceTypes(),
        ], $this->agentAssignmentOptions(), $this->sectionData()));
    }

    public function store(\App\Http\Requests\PropertyRequest $request)
    {
        $listingOwner = null;
        if (!$this->isAdmin()) {
            $owner = \App\Models\PortalUser::lockForUpdate()->findOrFail(Auth::guard('portal')->id());
            // Locked too, so two simultaneous adds can't both take the last slot of the plan.
            $listingOwner = $owner->isAgencyAgent()
                ? \App\Models\PortalUser::lockForUpdate()->findOrFail($owner->company_id)
                : $owner;

            if ($owner->status !== 'approved') {
                return redirect()->route('portal.dashboard')
                    ->with('error', 'Your account needs to be approved by Super Admin before you can add properties.');
            }

            if ($listingOwner->remainingPropertySlots() === 0) {
                return redirect()->route($this->routePrefix() . '.index')
                    ->with('error', "You've reached your plan's property limit. Upgrade your plan to add more listings.");
            }
        }


        $data = $request->only([
            'rera_id', 'listing_type', 'completion_status', 'property_type', 'category',
            'location', 'postal_code', 'latitude', 'longitude', 'bedrooms', 'bathrooms', 'sqft', 'price', 'currency',
        ]);
        // Always auto-generated (PROP001, ...) — never taken from user input, since it also becomes
        // the gallery folder/filename key (see storeGalleryImage()) and has to be final and unique.
        $data['reference_no'] = $this->generateReferenceNo();
        // null when Super Admin creates it directly (a house/MW Realty listing with no portal owner)
        // An agency agent's listing belongs to the agency (they stay its agent).
        $data['portal_user_id'] = $listingOwner?->id;
        $data['agent_id'] = $this->resolveAgentId($request, $listingOwner);
        $data['created_by_type'] = $this->isAdmin() ? 'admin' : ($this->viewer()->isAgency() ? 'agency' : 'agent');
        $data['created_by_id'] = $this->isAdmin() ? Auth::guard('cms')->id() : $this->viewer()->id;
        $data['translations'] = $request->input('translations', []);
        $data['status'] = $request->boolean('status') && ($this->isAdmin() || Auth::guard('portal')->user()->isApproved());
        // Featuring is booked afterwards from the listing card / Featured menu (dates + plan quota).
        $data['featured'] = false;
        $data['segment'] = $this->segment();
        $data['published_at'] = $request->input('published_at');
        // Column is NOT NULL with a schema default — an explicit null in the insert bypasses that
        // default, so it has to be resolved here instead of just passing the raw (possibly empty) input.
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
            foreach ($request->file('images') as $file) {
                $number++;
                $this->storeGalleryImage($file, $folder, $property->reference_no, $number);
                $sequence[] = $number;
            }
            $property->update([
                'image_path' => $folder,
                'image_sequence' => implode(',', $sequence),
                'image_next_number' => $number,
            ]);
        }

        $detailData = [
            'property_id' => $property->id,
            'amenities' => $this->processIconRepeater($request, 'amenities'),
            'easy_access' => $this->processIconRepeater($request, 'easy_access'),
            'property_attributes' => $this->processIconRepeater($request, 'property_attributes'),
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
        if ($request->hasFile('floor_plan_file')) {
            $detailData['floor_plan_file'] = app(\App\Services\ManagedFiles::class)->store($request->file('floor_plan_file'), 'properties/floor-plans');
        }
        PropertyDetail::create($detailData);

        foreach ($this->processFloorPlans($request) as $row) {
            $property->floorPlans()->create($row);
        }

        $property->nearbyPlaces()->sync($this->allowedNearbyPlaceIds($request));

        return redirect()->route($this->routePrefix() . '.index')->with('toast', $this->sectionData()['itemLabel'] . ' created successfully.');
    }

    /** Owner-only (delete, feature, reorder, status) — never an agency agent on the agency's listing. */
    protected function findOwned($id): Property
    {
        return Property::with(['details', 'images', 'floorPlans', 'nearbyPlaces', 'agent'])
            ->when($this->ownerId(), fn ($q, $ownerId) => $q->where('portal_user_id', $ownerId))
            ->findOrFail($id);
    }

    /** View / edit — the owner, or the agency agent the agency assigned this listing to. */
    protected function findAccessible($id): Property
    {
        return Property::with(['details', 'images', 'floorPlans', 'nearbyPlaces', 'agent', 'owner'])
            ->when($this->ownerId(), fn ($q) => $q->accessibleBy($this->viewer()))
            ->findOrFail($id);
    }

    public function edit($id)
    {
        $property = $this->findAccessible($id);
        if ($redirect = $this->redirectToOwnMenu($property, 'edit')) {
            return $redirect;
        }
        $languages = Language::where('status', true)->get();
        $filterOptions = $this->selectFilterOptions();
        return view('portal.properties.edit', array_merge([
            'property' => $property,
            'languages' => $languages,
            'filterOptions' => $filterOptions,
            'isAdmin' => $this->isAdmin(),
            'nearbyPlaceTypes' => $this->nearbyPlaceTypes(),
        ], $this->agentAssignmentOptions(), $this->sectionData()));
    }

    public function update(\App\Http\Requests\PropertyRequest $request, $id)
    {
        $property = $this->findAccessible($id);
        $previousAgentId = $property->agent_id;

        // reference_no is deliberately excluded — it's fixed at creation (read-only in the form)
        // since it's also the gallery folder/filename key; changing it would orphan existing photos.
        $data = $request->only([
            'rera_id', 'listing_type', 'completion_status', 'property_type', 'category',
            'location', 'postal_code', 'latitude', 'longitude', 'bedrooms', 'bathrooms', 'sqft', 'price', 'currency',
        ]);
        $data['translations'] = $request->input('translations', []);
        $data['status'] = $request->has('status');
        $data['agent_id'] = $this->resolveAgentId($request, $property->owner);
        $data['published_at'] = $request->input('published_at');
        $data['order_index'] = $request->input('order_index') ?: 0;
        // `featured` is deliberately not touched here: the form has no Featured switch (featuring
        // is booked with dates from the listing card / Featured menu), so reading it would
        // un-feature the listing on every save.
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

        $property->update($data);
        if ($request->hasFile('brochure')) {
            if ($property->brochure_path) {
                app(\App\Services\ManagedFiles::class)->delete($property->brochure_path);
            }
            $property->update(['brochure_path' => app(\App\Services\ManagedFiles::class)->store($request->file('brochure'), 'properties/brochures')]);
        }
        $this->recordAgentChange($property, $previousAgentId, $property->agent_id);

        if ($request->hasFile('images')) {
            // Backfills a reference_no for any property that predates this feature — shouldn't
            // normally happen since it's always set on create, but the gallery needs one either way.
            if (!$property->reference_no) {
                $property->update(['reference_no' => $this->generateReferenceNo()]);
            }
            $folder = app(\App\Services\PropertyGallery::class)->folderValue($property->image_path ?: ('properties/' . $property->reference_no));
            $number = $property->image_next_number;
            $sequence = $property->galleryNumbers();
            foreach ($request->file('images') as $file) {
                $number++;
                $this->storeGalleryImage($file, $folder, $property->reference_no, $number);
                $sequence[] = $number;
            }
            $property->update([
                'image_path' => $folder,
                'image_sequence' => implode(',', $sequence),
                'image_next_number' => $number,
            ]);
        }

        $detailData = [
            'amenities' => $this->processIconRepeater($request, 'amenities'),
            'easy_access' => $this->processIconRepeater($request, 'easy_access'),
            'property_attributes' => $this->processIconRepeater($request, 'property_attributes'),
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

        return redirect()->route($this->routePrefix() . '.index')->with('toast', $this->sectionData()['itemLabel'] . ' updated successfully.');
    }

    public function destroy($id)
    {
        $this->deletePropertyWithFiles($this->findOwned($id));

        return response()->json(['success' => true]);
    }

    /** "Bulk Actions" dropdown on the listing page: Mark Active / Mark Inactive / Delete Selected. */
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

        // A sold / rented listing stays off the website until it's reverted.
        $affected = $query->available()->update(['status' => $request->input('action') === 'active']);

        return response()->json(['success' => true, 'affected' => $affected]);
    }

    /**
     * Drag-and-drop reorder of the listing cards. `order` is the new id sequence of the current
     * page only, so the whole owner's list is renumbered with that page slice swapped in —
     * otherwise items on other pages would collide with the new positions.
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'order' => 'required|array|min:1',
            'order.*' => 'integer',
        ]);

        $allIds = $this->orderedListings(strict: true)->pluck('id');

        $pageIds = collect($request->input('order'))->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $allIds->contains($id))
            ->values();

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
     * "Move to top / bottom / position #N" on a listing card — works across pages (drag only
     * reaches the cards on the current page). `position` is 1-based over this menu's whole list.
     */
    public function move(Request $request, $id)
    {
        $request->validate(['position' => 'required']);
        $property = $this->findOwned($id);

        $allIds = $this->orderedListings(strict: true)->pluck('id');
        abort_unless($allIds->contains($property->id), 404);

        $position = match ($request->input('position')) {
            'top' => 1,
            'bottom' => $allIds->count(),
            default => (int) $request->input('position'),
        };
        if ($position < 1 || $position > $allIds->count()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['position' => 'Choose a position between 1 and ' . $allIds->count() . '.']);
        }

        $rest = $allIds->reject(fn ($listingId) => $listingId === $property->id)->values();
        $rest->splice($position - 1, 0, [$property->id]);
        $this->saveOrder($rest);

        return response()->json([
            'success' => true,
            'position' => $position,
            // The page the listing now sits on, so the UI can jump there.
            'page' => (int) ceil($position / self::PER_PAGE),
        ]);
    }

    /** Renumbers order_index 1..n in the given id order (only rows whose value actually changes). */
    protected function saveOrder(\Illuminate\Support\Collection $orderedIds): void
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

    protected function deletePropertyWithFiles(Property $property): void
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
                    if (!empty($row['icon'])) {
                        $files->delete($row['icon']);
                    }
                }
            }
        }

        $property->delete();
    }

    public function show($id)
    {
        $property = $this->findAccessible($id);
        if ($redirect = $this->redirectToOwnMenu($property, 'show')) {
            return $redirect;
        }
        $languages = Language::where('status', true)->get();

        return view('portal.properties.show', array_merge([
            'property' => $property,
            'languages' => $languages,
            'isAdmin' => $this->isAdmin(),
        ], $this->sectionData()));
    }

    /** $number is the gallery position suffix from the filename ({reference_no}-{number}.jpeg). */
    public function destroyImage($propertyId, $number)
    {
        $property = $this->findAccessible($propertyId);
        $number = (int) $number;
        $numbers = $property->galleryNumbers();

        if (in_array($number, $numbers, true) && $property->image_path) {
            app(\App\Services\PropertyGallery::class)->delete($property->image_path, $property->reference_no . '-' . $number . '.jpeg');
        }

        $property->update(['image_sequence' => implode(',', array_values(array_diff($numbers, [$number])))]);

        return response()->json(['success' => true]);
    }

    /** "Remove All" on the gallery — deletes the whole per-property image folder in one go. */
    public function destroyAllImages($propertyId)
    {
        $property = $this->findAccessible($propertyId);

        if ($property->image_path) {
            app(\App\Services\PropertyGallery::class)->deleteAll($property->image_path);
        }
        $property->update(['image_sequence' => null]);

        return response()->json(['success' => true]);
    }

    /**
     * "Reorder" drag-and-drop on the gallery — filenames never change, this just rewrites the
     * display-order list of numbers (image_sequence).
     */
    public function reorderImages(Request $request, $propertyId)
    {
        $property = $this->findAccessible($propertyId);

        $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer',
        ]);

        $submitted = array_map('intval', $request->input('order'));
        $valid = array_values(array_intersect($submitted, $property->galleryNumbers()));
        $property->update(['image_sequence' => implode(',', $valid)]);

        return response()->json(['success' => true]);
    }

    public function toggleStatus($id)
    {
        $property = $this->findOwned($id);
        abort_unless($this->isAdmin() || Auth::guard('portal')->user()->isApproved(), 403);
        if ($property->isSold()) {
            return response()->json(['message' => 'This listing is marked ' . $property->sold_type . '. Revert it to available first (Sold Listings).'], 422);
        }
        $property->status = !$property->status;
        $property->save();

        return response()->json(['success' => true]);
    }
}
