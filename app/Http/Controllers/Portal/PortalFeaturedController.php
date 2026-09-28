<?php

namespace App\Http\Controllers\Portal;

use App\Models\Property;
use App\Services\FeaturedListingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * CRM "Featured" menu: every featured (live or scheduled) listing across Properties and Commercial,
 * plus an "Add Premium" popup to book another one within the plan's quota. Booking and stopping go
 * through the shared portal.properties.feature / unfeature endpoints (PortalPropertyController).
 */
class PortalFeaturedController extends Controller
{
    /** Listings per request in the "Add Premium" picker. */
    protected const PICKER_PER_PAGE = 20;

    protected function isAdmin(): bool
    {
        return !Auth::guard('portal')->check() && (bool) Auth::guard('cms')->user()?->hasRole('superadmin');
    }

    protected function ownerId(): ?int
    {
        return Auth::guard('portal')->check() ? Auth::guard('portal')->user()->id : null;
    }

    public function index(Request $request, FeaturedListingService $featured)
    {
        $featured->sync();

        $owned = fn () => Property::query()->when($this->ownerId(), fn ($q, $ownerId) => $q->where('portal_user_id', $ownerId));
        $filter = in_array($request->input('status'), ['live', 'scheduled'], true) ? $request->input('status') : 'all';

        $listings = $owned()
            ->with('owner')
            ->where(fn ($q) => $q->where('featured', true)->orWhere(fn ($q) => $q->whereNotNull('featured_from')->where('featured_from', '>', now())))
            ->when($filter === 'live', fn ($q) => $q->where('featured', true))
            ->when($filter === 'scheduled', fn ($q) => $q->where('featured', false))
            ->orderByDesc('featured')
            ->orderBy('featured_until')
            ->orderBy('featured_from')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'live' => $owned()->where('featured', true)->count(),
            'scheduled' => $owned()->where('featured', false)->whereNotNull('featured_from')->where('featured_from', '>', now())->count(),
        ];

        return view('portal.featured.index', [
            'listings' => $listings,
            'editable' => $featured->editableIds($this->isAdmin() ? null : Auth::guard('portal')->user(), $listings->pluck('id')),
            'counts' => $counts,
            'filter' => $filter,
            'isAdmin' => $this->isAdmin(),
            'featuredQuota' => $this->isAdmin() ? null : $featured->quota(Auth::guard('portal')->user()),
        ]);
    }

    /** Most listings one "Add Premium" batch can book (Super Admin; owners are capped by their plan). */
    public const BATCH_LIMIT = 50;

    /**
     * "Add Premium" with several listings ticked: the same start/end dates for all of them,
     * all-or-nothing (see FeaturedListingService::featureMany).
     */
    public function store(Request $request, FeaturedListingService $featured)
    {
        $request->validate([
            'property_ids' => 'required|array|min:1|max:' . self::BATCH_LIMIT,
            'property_ids.*' => 'integer|distinct',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => ($this->isAdmin() ? 'nullable' : 'required') . '|date_format:Y-m-d',
        ]);

        $owner = $this->isAdmin() ? null : Auth::guard('portal')->user();
        abort_if($owner && !$owner->isApproved(), 403);

        $ids = collect($request->input('property_ids'))->map(fn ($id) => (int) $id)->unique();
        $properties = Property::whereIn('id', $ids)
            ->when($this->ownerId(), fn ($q, $ownerId) => $q->where('portal_user_id', $ownerId))
            ->get();
        abort_if($properties->count() !== $ids->count(), 404);
        // Keep the order the listings were ticked in, so a quota error names the right one.
        $properties = $ids->map(fn ($id) => $properties->firstWhere('id', $id))->values();

        $count = $featured->featureMany($owner, $properties, $request->input('start_date'), $request->input('end_date'));

        return response()->json(['success' => true, 'featured' => $count]);
    }

    /**
     * "Add Premium" picker: listings that can be booked, searched and paged on the server
     * (PICKER_PER_PAGE at a time, loaded as the list scrolls), so it stays fast however many
     * listings there are. Uses simplePaginate, so there's no COUNT(*) over the whole table.
     */
    public function eligible(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $search = trim((string) $request->input('q', ''));

        $page = Property::query()
            ->select(['id', 'portal_user_id', 'translations', 'reference_no', 'segment', 'status', 'image_path', 'image_sequence'])
            ->when($this->ownerId(), fn ($q, $ownerId) => $q->where('portal_user_id', $ownerId))
            // Not featured or scheduled already; owners can only feature active listings.
            ->where('featured', false)
            ->whereNull('featured_from')
            // …and no open booking either, even if the flags above ever drift out of step.
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('property_featurings')
                ->whereColumn('property_featurings.property_id', 'properties.id')
                ->whereNull('property_featurings.stopped_at')
                ->where('property_featurings.ends_at', '>', now()))
            ->when(!$this->isAdmin(), fn ($q) => $q->where('status', true))
            ->when($search !== '', fn ($q) => $q->portalSearch($search, $this->isAdmin()))
            ->with('owner:id,type,name,company_name')
            ->orderBy('order_index')->latest()->orderByDesc('id')
            ->simplePaginate(self::PICKER_PER_PAGE);

        return response()->json([
            'items' => collect($page->items())->map(fn (Property $p) => [
                'id' => $p->id,
                'title' => $p->getTranslation('title'),
                'ref' => $p->reference_no,
                'place' => collect(['community', 'city'])->map(fn ($f) => $p->getTranslation($f))->filter()->implode(', '),
                'thumb' => $p->galleryImages()[0]['url'] ?? null,
                'segment' => $p->segment,
                'active' => (bool) $p->status,
                'owner' => $this->isAdmin()
                    ? ($p->owner ? ($p->owner->type === 'company' ? ($p->owner->company_name ?: $p->owner->name) : $p->owner->name) : 'MW Realty')
                    : null,
            ])->values(),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ]);
    }
}
