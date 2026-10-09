<?php

namespace App\Http\Controllers\Crm\Properties;

use App\Http\Controllers\Crm\Concerns\ScopesListings;
use App\Models\Property;
use App\Services\FeaturedListingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @group CRM Premium
 *
 * The Premium menu: every premium (live or scheduled) listing across Properties and Commercial, plus
 * "Add Premium" to book several listings at once within the plan's quota. A single listing is booked,
 * re-dated or stopped through /properties/{id}/feature (PropertyFeatureController).
 */
class PremiumController extends Controller
{
    use ScopesListings;

    /** Listings per request in the "Add Premium" picker. */
    private const PICKER_PER_PAGE = 20;

    /** Most listings one "Add Premium" batch can book (Super Admin; owners are capped by their plan). */
    public const BATCH_LIMIT = 50;

    public function __construct(private readonly FeaturedListingService $featured)
    {
    }

    /**
     * List premium listings
     *
     * Live first (ending soonest), then scheduled — 20 per page, with the counts and the plan quota.
     *
     * @queryParam status string all | live | scheduled. Example: live
     * @queryParam page integer Example: 1
     */
    public function index(Request $request)
    {
        $this->featured->sync();

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
            ->paginate(20);

        $editable = $this->featured->editableIds($this->isAdmin() ? null : $this->viewer(), collect($listings->items())->pluck('id'));
        $quota = $this->isAdmin() ? null : $this->featured->quota($this->viewer());

        return response()->json([
            'data' => collect($listings->items())->map(function (Property $p) use ($editable) {
                $live = (bool) $p->featured;

                return [
                    'id' => $p->id,
                    'segment' => $p->segment,
                    'title' => $p->getTranslation('title'),
                    'thumb' => $p->galleryImages()[0]['url'] ?? null,
                    'reference_no' => $p->reference_no,
                    'status' => (bool) $p->status,
                    'owner' => $p->owner ? ($p->owner->type === 'company' ? ($p->owner->company_name ?: $p->owner->name) : $p->owner->name) : 'MW Realty',
                    'live' => $live,
                    'featured_from' => $p->featured_from?->toDateString(),
                    'featured_until' => $p->featured_until?->toDateString(),
                    'starts_in' => !$live && $p->featured_from ? $p->featured_from->diffForHumans() : null,
                    'days_left' => $live && $p->featured_until ? max(0, (int) ceil(now()->floatDiffInDays($p->featured_until))) : null,
                    'editable' => isset($editable[$p->id]),
                ];
            }),
            'meta' => [
                'current_page' => $listings->currentPage(), 'last_page' => $listings->lastPage(), 'total' => $listings->total(),
                'from' => $listings->firstItem(), 'to' => $listings->lastItem(),
            ],
            'filter' => $filter,
            'counts' => [
                'live' => $owned()->where('featured', true)->count(),
                'scheduled' => $owned()->where('featured', false)->whereNotNull('featured_from')->where('featured_from', '>', now())->count(),
            ],
            'is_admin' => $this->isAdmin(),
            'featured_quota' => $quota ? $quota + ['resets' => now()->addMonthNoOverflow()->startOfMonth()->format('d M')] : null,
            'feature_max_days' => FeaturedListingService::MAX_DAYS,
            'batch_limit' => self::BATCH_LIMIT,
        ]);
    }

    /**
     * Add Premium (several listings)
     *
     * The same start / end dates for every ticked listing, all-or-nothing (FeaturedListingService::featureMany).
     *
     * @bodyParam property_ids integer[] required Example: [12, 14]
     * @bodyParam start_date string required Y-m-d. Example: 2026-10-10
     * @bodyParam end_date string Y-m-d — required except for Super Admin. Example: 2026-11-08
     */
    public function store(Request $request)
    {
        $request->validate([
            'property_ids' => 'required|array|min:1|max:' . self::BATCH_LIMIT,
            'property_ids.*' => 'integer|distinct',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => ($this->isAdmin() ? 'nullable' : 'required') . '|date_format:Y-m-d',
        ]);

        $owner = $this->isAdmin() ? null : $this->viewer();
        abort_if($owner && !$owner->isApproved(), 403);

        $ids = collect($request->input('property_ids'))->map(fn ($id) => (int) $id)->unique();
        $properties = Property::whereIn('id', $ids)
            ->when($this->ownerId(), fn ($q, $ownerId) => $q->where('portal_user_id', $ownerId))
            ->get();
        abort_if($properties->count() !== $ids->count(), 404);
        // Keep the order the listings were ticked in, so a quota error names the right one.
        $properties = $ids->map(fn ($id) => $properties->firstWhere('id', $id))->values();

        $count = $this->featured->featureMany($owner, $properties, $request->input('start_date'), $request->input('end_date'));

        return response()->json(['success' => true, 'featured' => $count]);
    }

    /**
     * Listings that can be made premium
     *
     * The "Add Premium" picker, searched and paged on the server (20 at a time). Owners only see
     * active listings that aren't premium or scheduled already.
     *
     * @queryParam q string Example: marina
     * @queryParam page integer Example: 1
     */
    public function eligible(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $search = trim((string) $request->input('q', ''));

        $page = Property::query()
            ->select(['id', 'portal_user_id', 'translations', 'reference_no', 'segment', 'status', 'image_path', 'image_sequence'])
            ->when($this->ownerId(), fn ($q, $ownerId) => $q->where('portal_user_id', $ownerId))
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
