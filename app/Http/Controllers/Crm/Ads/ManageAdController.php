<?php

namespace App\Http\Controllers\Crm\Ads;

use App\Http\Controllers\Crm\Concerns\ScopesPortalOwner;
use App\Models\AdPlacement;
use App\Models\CmsKit\Ad;
use App\Models\CmsKit\HouseAd;
use App\Models\CmsKit\Language;
use App\Models\Plan;
use App\Models\Property;
use App\Services\AdBookingService;
use App\Services\ManagedFiles;
use App\Support\ValidatesImageDimensions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * @group CRM Advertisements (Super Admin)
 *
 * Super Admin's side of CRM › Advertisements (it replaced CMS › Ad Management): MW Realty's own ads
 * (any page, an optional date window, on/off, slide order), the review of the ads agencies / agents
 * book (approve, or reject with a reason — the client is refunded), and per page how many client
 * ads run at once and their credit prices. Client bookings themselves: AdController / AdBookingService.
 */
class ManageAdController extends Controller
{
    use ScopesPortalOwner, ValidatesImageDimensions;

    private const HOUSE_STATES = ['live', 'scheduled', 'ended', 'off'];
    private const CLIENT_STATES = ['pending', 'upcoming', 'live', 'past', 'all'];

    public function __construct(private readonly AdBookingService $ads)
    {
    }

    /**
     * Overview + one tab's list
     *
     * `tab=house` (MW Realty's ads, 15 per page, slide order) or `tab=client` (bookings, 20 per page).
     * Always includes the 30-day totals and the counts for both tabs.
     *
     * @queryParam tab string house | client. Example: client
     * @queryParam state string house: all | live | scheduled | ended | off; client: pending | upcoming | live | past | all. Example: pending
     * @queryParam placement string A page key. Example: home
     * @queryParam q string Name or client. Example: marina
     * @queryParam page integer Example: 1
     */
    public function index(Request $request)
    {
        $this->ensureAdmin();
        $tab = $request->input('tab') === 'client' ? 'client' : 'house';
        $placement = array_key_exists((string) $request->input('placement'), Ad::PLACEMENTS) ? $request->input('placement') : null;
        $search = trim((string) $request->input('q', ''));

        if ($tab === 'house') {
            $state = in_array($request->input('state'), self::HOUSE_STATES, true) ? $request->input('state') : 'all';
            $list = $this->houseFiltered(HouseAd::query(), $state)
                ->when($placement, fn ($q) => $q->where('placement', $placement))
                ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->orderBy('order_index')->orderBy('id')
                ->paginate(15);
        } else {
            $state = in_array($request->input('state'), self::CLIENT_STATES, true) ? $request->input('state') : 'pending';
            $list = $this->clientFiltered(Ad::client(), $state)
                ->with(['owner:id,type,name,company_name,email,avatar', 'property:id,slug,translations,reference_no,status'])
                ->when($placement, fn ($q) => $q->where('placement', $placement))
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('owner', fn ($o) => $o->where('name', 'like', "%{$search}%")->orWhere('company_name', 'like', "%{$search}%"))))
                ->orderBy($state === 'past' ? 'ends_at' : 'starts_at', $state === 'past' ? 'desc' : 'asc')->orderBy('id')
                ->paginate(20);
        }
        $stats = $this->ads->totalsByAd(collect($list->items())->pluck('id'));

        return response()->json([
            'is_admin' => true,
            'tab' => $tab,
            'state' => $state,
            'data' => collect($list->items())->map(fn (Ad $ad) => $this->row($ad, $stats[$ad->id] ?? null)),
            'meta' => [
                'current_page' => $list->currentPage(), 'last_page' => $list->lastPage(), 'total' => $list->total(),
                'from' => $list->firstItem(), 'to' => $list->lastItem(),
            ],
            'counts' => [
                'house' => collect(['all', ...self::HOUSE_STATES])->mapWithKeys(fn ($s) => [$s => $this->houseFiltered(HouseAd::query(), $s)->count()]),
                'client' => collect(self::CLIENT_STATES)->mapWithKeys(fn ($s) => [$s => $this->clientFiltered(Ad::client(), $s)->count()]),
            ],
            'summary' => $this->ads->totals(Ad::query()->pluck('id'), now()->subDays(30))
                + ['live' => Ad::currentlyRunning()->count()],
            'placements' => collect(Ad::PLACEMENTS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
        ]);
    }

    /** Form options for an MW Realty ad: every page, the languages, image limits. */
    public function formOptions()
    {
        $this->ensureAdmin();

        return response()->json([
            'is_admin' => true,
            'placements' => collect(Ad::PLACEMENTS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'languages' => Language::where('status', true)->orderByDesc('is_default')->get(['code', 'name']),
            'timezone' => AdBookingService::TIMEZONE,
            'text_limits' => Ad::TEXT_LIMITS,
            'images' => [
                'image' => config('cms-kit.images.ads.image', []),
                'mobile_image' => config('cms-kit.images.ads.mobile_image', []),
            ],
        ]);
    }

    /** Any active listing, for an ad that links to one — RemoteSelect's `{data, next_page}`. */
    public function properties(Request $request)
    {
        $this->ensureAdmin();
        $request->validate(['q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $search = trim((string) $request->input('q', ''));

        $page = Property::query()->select(['id', 'translations', 'reference_no'])->where('status', true)
            ->when($search !== '', fn ($q) => $q->portalSearch($search, true))
            ->orderByDesc('id')->simplePaginate(20);

        return response()->json([
            'data' => collect($page->items())->map(fn (Property $p) => [
                'id' => $p->id,
                'name' => trim(($p->getTranslation('title') ?: 'Untitled') . ($p->reference_no ? ' · ' . $p->reference_no : '')),
            ])->values(),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ]);
    }

    /**
     * One ad (MW Realty's or a client's) with its report.
     *
     * @urlParam id integer required Example: 12
     */
    public function show($id)
    {
        $this->ensureAdmin();
        $ad = Ad::with(['owner', 'property:id,translations,reference_no,slug,status'])->findOrFail($id);
        $report = $this->ads->report($ad);
        $tz = AdBookingService::TIMEZONE;

        return response()->json([
            'is_admin' => true,
            'ad' => $this->row($ad, $report['totals']) + [
                'image_url' => media_url($ad->image),
                'mobile_image_url' => $ad->mobile_image ? media_url($ad->mobile_image) : null,
                'image_alt' => $ad->image_alt,
                'translations' => (object) ($ad->translations ?? []),
                'link_type' => $ad->link_type,
                'property_id' => $ad->property_id,
                'link_url' => $ad->link_url,
                'status' => (bool) $ad->status,
                'start_date' => $ad->starts_at?->copy()->timezone($tz)->toDateString(),
                'end_date' => $ad->ends_at?->copy()->timezone($tz)->toDateString(),
                'reviewed_at' => $ad->reviewed_at?->toIso8601String(),
                'reviewed_by' => $ad->reviewed_by,
                'preview' => $ad->payload(),
            ],
            'report' => $report,
            'timezone' => $tz,
        ]);
    }

    /**
     * Add an MW Realty ad
     *
     * Multipart. Runs on its page from `start_date` to the end of `end_date` (Dubai days; both
     * optional = always on) while `status` is on. New ads go first in their page's slide order.
     */
    public function store(Request $request)
    {
        $this->ensureAdmin();
        $request->validate($this->rules(true));
        $this->validateImages($request);

        $files = app(ManagedFiles::class);
        $data = $this->fields($request) + [
            'image' => $files->store($request->file('image'), 'ads'),
            'mobile_image' => $request->hasFile('mobile_image') ? $files->store($request->file('mobile_image'), 'ads') : null,
            'review_status' => Ad::APPROVED,
            'order_index' => 1,
        ];
        HouseAd::query()->increment('order_index');
        $ad = HouseAd::create($data);

        return response()->json(['success' => true, 'id' => $ad->id, 'message' => 'Ad added.'], 201);
    }

    /** Change an MW Realty ad (multipart POST with `_method=PUT`). */
    public function update(Request $request, $id)
    {
        $this->ensureAdmin();
        $ad = HouseAd::findOrFail($id);
        $request->validate($this->rules(false) + ['remove_mobile_image' => 'nullable|boolean']);
        $this->validateImages($request);

        $files = app(ManagedFiles::class);
        $data = $this->fields($request);
        if ($request->hasFile('image')) {
            $files->delete(Ad::ownFile($ad->image));
            $data['image'] = $files->store($request->file('image'), 'ads');
        }
        if ($request->hasFile('mobile_image') || $request->boolean('remove_mobile_image')) {
            $files->delete(Ad::ownFile($ad->mobile_image));
            $data['mobile_image'] = $request->hasFile('mobile_image') ? $files->store($request->file('mobile_image'), 'ads') : null;
        }
        $ad->update($data);

        return response()->json(['success' => true, 'message' => 'Ad updated.']);
    }

    /** Delete an MW Realty ad (client ads are rejected instead, so they get refunded). */
    public function destroy($id)
    {
        $this->ensureAdmin();
        $ad = HouseAd::findOrFail($id);
        app(ManagedFiles::class)->delete(Ad::ownFile($ad->image));
        app(ManagedFiles::class)->delete(Ad::ownFile($ad->mobile_image));
        $ad->delete();
        $this->renumber();

        return response()->json(['success' => true, 'message' => 'Ad deleted.']);
    }

    /** Switch an MW Realty ad on / off. */
    public function toggle($id)
    {
        $this->ensureAdmin();
        $ad = HouseAd::findOrFail($id);
        $ad->update(['status' => !$ad->status]);

        return response()->json(['success' => true, 'status' => (bool) $ad->status, 'message' => $ad->status ? 'Ad switched on.' : 'Ad switched off.']);
    }

    /**
     * Move in the slide order
     *
     * Swaps the ad with the one before / after it on the same page (the slider shows them in this
     * order, after any client ads).
     *
     * @bodyParam direction string required up | down. Example: up
     */
    public function move(Request $request, $id)
    {
        $this->ensureAdmin();
        $request->validate(['direction' => 'required|in:up,down']);
        $this->renumber();
        $ad = HouseAd::findOrFail($id);
        $up = $request->input('direction') === 'up';

        $neighbour = HouseAd::where('placement', $ad->placement)
            ->where('order_index', $up ? '<' : '>', $ad->order_index)
            ->orderBy('order_index', $up ? 'desc' : 'asc')->first();
        if ($neighbour) {
            [$a, $b] = [$ad->order_index, $neighbour->order_index];
            $ad->update(['order_index' => $b]);
            $neighbour->update(['order_index' => $a]);
        }

        return response()->json(['success' => true, 'moved' => (bool) $neighbour]);
    }

    /** Approve a client's ad — it runs in its booked hours. */
    public function approve($id)
    {
        $this->ensureAdmin();
        $this->ads->approve(Ad::client()->findOrFail($id), $this->actorName());

        return response()->json(['success' => true, 'message' => 'Ad approved — it runs in its booked time.']);
    }

    /**
     * Reject / take down a client's ad
     *
     * The client sees the reason. Not run yet → their credits / plan ad come back; live → it comes
     * off the website now, and the time used isn't refunded.
     *
     * @bodyParam note string required Example: The banner text is too small to read.
     */
    public function reject(Request $request, $id)
    {
        $this->ensureAdmin();
        $request->validate(['note' => 'required|string|max:500']);
        $this->ads->reject(Ad::client()->findOrFail($id), $request->input('note'), $this->actorName());

        return response()->json(['success' => true, 'message' => 'Ad rejected — the client has been told why.']);
    }

    /** Per page: open to clients, client ads at once, credits per hour / day. */
    public function placements()
    {
        $this->ensureAdmin();
        $running = Ad::currentlyRunning()->selectRaw('placement, COUNT(*) AS total')->groupBy('placement')->pluck('total', 'placement');

        return response()->json([
            'data' => AdPlacement::catalog()->map(fn (AdPlacement $p) => [
                'placement' => $p->placement,
                'label' => $p->label(),
                'client_bookable' => $p->client_bookable,
                'max_slots' => $p->max_slots,
                'credits_per_hour' => $p->credits_per_hour,
                'credits_per_day' => $p->credits_per_day,
                'running' => (int) ($running[$p->placement] ?? 0),
            ])->values(),
        ]);
    }

    /** @bodyParam placements object required {page key: {client_bookable, max_slots, credits_per_hour, credits_per_day}}. No-example */
    public function updatePlacements(Request $request)
    {
        $this->ensureAdmin();
        $request->validate([
            'placements' => 'required|array',
            'placements.*.client_bookable' => 'boolean',
            'placements.*.max_slots' => 'required|integer|min:0|max:50',
            'placements.*.credits_per_hour' => 'required|integer|min:0|max:1000000',
            'placements.*.credits_per_day' => 'required|integer|min:0|max:1000000',
        ]);

        $catalog = AdPlacement::catalog();
        foreach ((array) $request->input('placements') as $key => $values) {
            $catalog->get($key)?->update([
                'client_bookable' => (bool) ($values['client_bookable'] ?? false),
                'max_slots' => (int) $values['max_slots'],
                'credits_per_hour' => (int) $values['credits_per_hour'],
                'credits_per_day' => (int) $values['credits_per_day'],
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Placements saved.']);
    }

    // ------------------------------------------------------------------ helpers

    private function ensureAdmin(): void
    {
        abort_unless($this->isAdmin(), 403, 'Only MW Realty can manage this.');
    }

    private function houseFiltered($query, string $state)
    {
        $now = now();

        return match ($state) {
            'live' => $query->currentlyRunning(),
            'scheduled' => $query->where('status', true)->where('starts_at', '>', $now),
            'ended' => $query->whereNotNull('ends_at')->where('ends_at', '<=', $now),
            'off' => $query->where('status', false)->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now)),
            default => $query,
        };
    }

    private function clientFiltered($query, string $state)
    {
        $now = now();

        return match ($state) {
            'pending' => $query->where('review_status', Ad::PENDING)->whereNull('cancelled_at')->where('ends_at', '>', $now),
            'upcoming' => $query->where('review_status', Ad::APPROVED)->whereNull('cancelled_at')->where('starts_at', '>', $now),
            'live' => $query->currentlyRunning(),
            'past' => $query->where(fn ($q) => $q->whereNotNull('cancelled_at')->orWhere('review_status', Ad::REJECTED)->orWhere('ends_at', '<=', $now)),
            default => $query,
        };
    }

    private function row(Ad $ad, ?array $stats): array
    {
        $state = $ad->state();
        $client = $ad->isClientAd();

        return [
            'id' => $ad->id,
            'name' => $ad->name,
            'thumb' => media_url($ad->image),
            'is_client' => $client,
            'client' => $client ? [
                'name' => $ad->owner?->displayName(),
                'type' => $ad->owner?->type === 'company' ? 'Agency' : 'Agent',
                'email' => $ad->owner?->email,
            ] : null,
            'placement' => $ad->placement,
            'placement_label' => Ad::PLACEMENTS[$ad->placement] ?? $ad->placement,
            'state' => $state,
            'status' => (bool) $ad->status,
            'order' => $client ? null : $ad->order_index,
            'starts_at' => $ad->starts_at?->toIso8601String(),
            'ends_at' => $ad->ends_at?->toIso8601String(),
            'duration_label' => $ad->duration_hours ? Plan::adDurationLabel($ad->duration_hours) : null,
            'paid_with' => $ad->paid_with,
            'credits' => $ad->credits,
            'review_note' => $ad->review_note,
            'reviewed_by' => $ad->reviewed_by,
            'link' => [
                'type' => $ad->link_type,
                'label' => match ($ad->link_type) {
                    Ad::LINK_PROPERTY => $ad->property ? ($ad->property->getTranslation('title') ?: $ad->property->reference_no) : 'Listing removed',
                    Ad::LINK_PROFILE => 'Their profile page',
                    default => $ad->link_url,
                },
                'url' => ($url = $ad->resolvedLink()) && str_starts_with($url, '/') ? url($url) : $url,
            ],
            'stats' => $stats ?? ['impressions' => 0, 'clicks' => 0, 'ctr' => 0],
            'can_approve' => $client && $state === 'pending',
            'can_reject' => $client && in_array($state, ['pending', 'scheduled', 'live'], true),
            'editable' => !$client,
        ];
    }

    private function rules(bool $creating): array
    {
        return [
            'name' => 'required|string|max:255',
            'placement' => ['required', Rule::in(array_keys(Ad::PLACEMENTS))],
            'link_type' => 'required|in:url,property',
            'property_id' => 'nullable|required_if:link_type,property|integer',
            // A full URL, or a path on this site (e.g. /properties).
            'link_url' => ['nullable', 'string', 'max:500', 'regex:#^(https?://|/)#i'],
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'status' => 'nullable|boolean',
            'image' => ($creating ? 'required' : 'nullable') . '|image|max:' . (int) (config('cms-kit.images.ads.image.max_size') ?? 4096),
            'mobile_image' => 'nullable|image|max:' . (int) (config('cms-kit.images.ads.mobile_image.max_size') ?? 4096),
            'image_alt' => 'nullable|string|max:255',
            'translations' => 'nullable|array',
            'translations.*.eyebrow' => 'nullable|string|max:' . Ad::TEXT_LIMITS['eyebrow'],
            'translations.*.title' => 'nullable|string|max:' . Ad::TEXT_LIMITS['title'],
            'translations.*.text' => 'nullable|string|max:' . Ad::TEXT_LIMITS['text'],
        ];
    }

    private function validateImages(Request $request): void
    {
        $this->validateImageWithinLimits($request, 'image', config('cms-kit.images.ads.image', []), 'Banner');
        $this->validateImageWithinLimits($request, 'mobile_image', config('cms-kit.images.ads.mobile_image', []), 'Phone banner');
    }

    /** The columns an MW Realty ad's form sets (dates as whole Dubai days). */
    private function fields(Request $request): array
    {
        $tz = AdBookingService::TIMEZONE;
        $link = $request->input('link_type') === 'property'
            ? ['link_type' => Ad::LINK_PROPERTY, 'link_url' => null,
               'property_id' => Property::where('status', true)->find((int) $request->input('property_id'))?->id
                   ?? throw ValidationException::withMessages(['property_id' => 'Pick an active listing.'])]
            : ['link_type' => Ad::LINK_URL, 'link_url' => $request->input('link_url') ?: null, 'property_id' => null];

        $text = collect($request->input('translations', []))
            ->map(fn ($fields) => array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : null, (array) $fields), fn ($v) => $v !== null && $v !== ''))
            ->filter()->all();

        return $link + [
            'name' => $request->input('name'),
            'placement' => $request->input('placement'),
            'image_alt' => $request->input('image_alt'),
            'translations' => $text ?: null,
            'status' => $request->boolean('status', true),
            'starts_at' => $request->filled('start_date') ? Carbon::parse($request->input('start_date'), $tz)->startOfDay()->utc() : null,
            'ends_at' => $request->filled('end_date') ? Carbon::parse($request->input('end_date'), $tz)->endOfDay()->utc() : null,
        ];
    }

    /** Slide order 1…n over MW Realty's ads. */
    private function renumber(): void
    {
        HouseAd::orderBy('order_index')->orderBy('id')->get(['id', 'order_index'])->values()
            ->each(fn ($ad, $i) => $ad->order_index === $i + 1 ?: HouseAd::whereKey($ad->id)->update(['order_index' => $i + 1]));
    }
}
