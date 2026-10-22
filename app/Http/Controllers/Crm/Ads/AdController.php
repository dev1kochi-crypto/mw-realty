<?php

namespace App\Http\Controllers\Crm\Ads;

use App\Http\Controllers\Crm\Concerns\ScopesPortalOwner;
use App\Models\AdPlacement;
use App\Models\CmsKit\Ad;
use App\Models\CmsKit\Language;
use App\Models\Plan;
use App\Models\PortalUser;
use App\Models\Property;
use App\Services\AdBookingService;
use App\Support\ValidatesImageDimensions;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * @group CRM Advertisements
 *
 * Website advertising for agencies / independent agents whose plan includes it: book a banner on a
 * page of the website (it runs in that page's ad slider) for some hours or days, follow it through
 * MW Realty's review, and see how often it was shown and clicked. Rules: App\Services\AdBookingService.
 * Times are sent and shown in Dubai time.
 */
class AdController extends Controller
{
    use ScopesPortalOwner, ValidatesImageDimensions;

    public function __construct(private readonly AdBookingService $ads)
    {
    }

    /**
     * List ads
     *
     * The account's ads, newest booking first (20 per page), with impressions / clicks per ad, the
     * last 30 days' totals and the plan allowance. `quota.included` false = the plan has no
     * advertising (show the upgrade card).
     *
     * @queryParam status string all | live | upcoming | past. Example: live
     * @queryParam page integer Example: 1
     */
    public function index(Request $request)
    {
        $account = $this->account();
        $filter = in_array($request->input('status'), ['live', 'upcoming', 'past'], true) ? $request->input('status') : 'all';
        $mine = fn () => Ad::where('portal_user_id', $account->id);

        $ads = $this->filtered($mine(), $filter)
            ->with('property:id,translations,reference_no')
            ->orderByDesc('starts_at')->orderByDesc('id')
            ->paginate(20);
        $stats = $this->ads->totalsByAd(collect($ads->items())->pluck('id'));

        return response()->json([
            'data' => collect($ads->items())->map(fn (Ad $ad) => $this->row($ad, $stats[$ad->id] ?? null)),
            'meta' => [
                'current_page' => $ads->currentPage(), 'last_page' => $ads->lastPage(), 'total' => $ads->total(),
                'from' => $ads->firstItem(), 'to' => $ads->lastItem(),
            ],
            'filter' => $filter,
            'counts' => collect(['live', 'upcoming', 'past'])->mapWithKeys(fn ($f) => [$f => $this->filtered($mine(), $f)->count()])
                ->put('all', $mine()->count()),
            'summary' => $this->ads->totals($mine()->pluck('id'), now()->subDays(30)),
            'quota' => $this->ads->quota($account),
        ]);
    }

    /**
     * Booking options
     *
     * Everything the "Book an ad" form needs: the pages ads can run on (with their credit prices and
     * how many run at once), the lengths the plan allows, the allowance and credit balance, the
     * active languages for the banner text, and the earliest start.
     */
    public function formOptions()
    {
        $account = $this->account();

        return response()->json([
            'placements' => AdPlacement::catalog()->where('client_bookable', true)->where('max_slots', '>', 0)->map(fn (AdPlacement $p) => [
                'value' => $p->placement,
                'label' => $p->label(),
                'max_slots' => $p->max_slots,
                'credits_per_hour' => $p->credits_per_hour,
                'credits_per_day' => $p->credits_per_day,
            ])->values(),
            'durations' => collect($this->ads->durations($account))->map(fn ($h) => ['hours' => $h, 'label' => Plan::adDurationLabel($h)]),
            'all_durations' => collect(AdBookingService::DURATIONS)->map(fn ($h) => ['hours' => $h, 'label' => Plan::adDurationLabel($h)]),
            'quota' => $this->ads->quota($account),
            'languages' => Language::where('status', true)->orderByDesc('is_default')->get(['code', 'name']),
            'earliest_start' => $this->ads->earliestStart()->format('Y-m-d H:i'),
            'max_days_ahead' => AdBookingService::MAX_DAYS_AHEAD,
            'timezone' => AdBookingService::TIMEZONE,
            'profile_url' => $account->slug ? url(($account->type === 'company' ? '/agency-details/' : '/agent-details/') . $account->slug) : null,
            'text_limits' => Ad::TEXT_LIMITS,
            'images' => [
                'image' => config('cms-kit.images.ads.image', []),
                'mobile_image' => config('cms-kit.images.ads.mobile_image', []),
            ],
        ]);
    }

    /**
     * Free slots
     *
     * For each hour of a day (Dubai time) on a page: how many client ads are booked and how many
     * slots are still free. `past` hours can't be booked any more.
     *
     * @queryParam placement string required Example: home
     * @queryParam date string required Y-m-d. Example: 2026-10-14
     */
    public function availability(Request $request)
    {
        $this->account();
        $data = $request->validate([
            'placement' => ['required', Rule::in(array_keys(Ad::PLACEMENTS))],
            'date' => 'required|date_format:Y-m-d',
        ]);
        $placement = AdPlacement::for($data['placement']);
        abort_unless($placement?->client_bookable, 404);

        return response()->json([
            'placement' => $placement->placement,
            'max_slots' => $placement->max_slots,
            'hours' => $this->ads->availability($placement, $data['date']),
        ]);
    }

    /**
     * Listings an ad can link to
     *
     * The account's active listings, searched and paged on the server (20 at a time) — RemoteSelect's
     * `{data: [{id, name}], next_page}`.
     *
     * @queryParam q string Example: marina
     * @queryParam page integer Example: 1
     */
    public function properties(Request $request)
    {
        $account = $this->account();
        $request->validate(['q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $search = trim((string) $request->input('q', ''));

        $page = Property::query()
            ->select(['id', 'translations', 'reference_no'])
            ->where('portal_user_id', $account->id)
            ->where('status', true)
            ->when($search !== '', fn ($q) => $q->portalSearch($search, false))
            ->orderByDesc('id')
            ->simplePaginate(20);

        return response()->json([
            'data' => collect($page->items())->map(fn (Property $p) => [
                'id' => $p->id,
                'name' => trim(($p->getTranslation('title') ?: 'Untitled') . ($p->reference_no ? ' · ' . $p->reference_no : '')),
            ])->values(),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ]);
    }

    /**
     * Book an ad
     *
     * Multipart form. The ad waits for MW Realty's review and runs, once approved, from `start` for
     * `hours`. `pay_with=plan` uses the monthly allowance; `credits` spends credits (refunded if it's
     * rejected or cancelled before it runs).
     *
     * @bodyParam name string required Only you see it. Example: Marina Heights launch
     * @bodyParam placement string required A page from the booking options. Example: home
     * @bodyParam start string required Y-m-d H:i, Dubai time, on the hour. Example: 2026-10-14 18:00
     * @bodyParam hours integer required One of the plan's lengths. Example: 6
     * @bodyParam pay_with string required plan | credits. Example: plan
     * @bodyParam link_type string required property | profile | url. Example: property
     * @bodyParam property_id integer With link_type=property. Example: 111
     * @bodyParam link_url string With link_type=url, https://… No-example
     * @bodyParam image file required The banner (wide image).
     * @bodyParam mobile_image file Optional banner for phones.
     * @bodyParam image_alt string Example: Marina Heights tower at sunset
     * @bodyParam translations object Optional text over the image per language: {code: {eyebrow, title, text}}. No-example
     */
    public function store(Request $request)
    {
        $this->account();
        $request->validate($this->rules(true) + [
            'placement' => ['required', Rule::in(array_keys(Ad::PLACEMENTS))],
            'start' => 'required|date_format:Y-m-d H:i',
            'hours' => ['required', 'integer', Rule::in(AdBookingService::DURATIONS)],
            'pay_with' => 'required|in:plan,credits',
        ]);
        $this->validateImages($request);

        $ad = $this->ads->book($this->owner(), $this->input($request) + $request->only(['placement', 'start', 'hours', 'pay_with']),
            $request->file('image'), $request->file('mobile_image'));

        return response()->json(['success' => true, 'id' => $ad->id, 'message' => 'Ad booked — MW Realty will review it before it goes live.'], 201);
    }

    /**
     * One ad
     *
     * The ad with its booking, review state and full report: totals, an hourly series over its run,
     * totals per day and its busiest hours.
     *
     * @urlParam id integer required Example: 12
     */
    public function show($id)
    {
        $ad = $this->find($id);
        $report = $this->ads->report($ad);

        return response()->json([
            'ad' => $this->row($ad, $report['totals']) + [
                'image_url' => media_url($ad->image),
                'mobile_image_url' => $ad->mobile_image ? media_url($ad->mobile_image) : null,
                'image_alt' => $ad->image_alt,
                'translations' => (object) ($ad->translations ?? []),
                'link_type' => $ad->link_type,
                'property_id' => $ad->property_id,
                'link_url' => $ad->link_url,
                'reviewed_at' => $ad->reviewed_at?->toIso8601String(),
                'preview' => $ad->payload(),
            ],
            'report' => $report,
            'timezone' => AdBookingService::TIMEZONE,
        ]);
    }

    /**
     * Change an ad
     *
     * Banner, text and link only, before the ad starts (send as multipart POST with `_method=PUT`).
     * The page, time and payment stay; it goes back to MW Realty's review.
     *
     * @urlParam id integer required Example: 12
     * @bodyParam remove_mobile_image boolean Drop the phone banner. Example: false
     */
    public function update(Request $request, $id)
    {
        $ad = $this->find($id);
        $request->validate($this->rules(false) + ['remove_mobile_image' => 'nullable|boolean']);
        $this->validateImages($request);

        $this->ads->updateCreative($ad, $this->input($request), $request->file('image'), $request->file('mobile_image'), $request->boolean('remove_mobile_image'));

        return response()->json(['success' => true, 'message' => 'Ad updated — it will be reviewed again before it goes live.']);
    }

    /**
     * Cancel / stop an ad
     *
     * Not started yet (or still in review): cancelled, with its credits / allowance given back.
     * Live: stops now — the time already used isn't refunded.
     *
     * @urlParam id integer required Example: 12
     */
    public function destroy($id)
    {
        $result = $this->ads->cancel($this->find($id));

        return response()->json([
            'success' => true,
            'result' => $result,
            'message' => $result === 'stopped' ? 'Ad stopped.' : 'Ad cancelled.',
        ]);
    }

    // ------------------------------------------------------------------ helpers

    /** The account that books ads; Super Admin and agency agents don't. */
    private function account(): PortalUser
    {
        $user = $this->owner();
        abort_unless($user, 404, 'Super Admin manages ads under /ads/manage.');
        abort_unless(AdBookingService::usableBy($user), 403, 'Advertising is managed by your agency.');

        return $user;
    }

    private function find($id): Ad
    {
        return Ad::where('portal_user_id', $this->account()->id)->with(['property:id,translations,reference_no,slug,status', 'owner'])->findOrFail($id);
    }

    private function filtered($query, string $filter)
    {
        $now = now();

        return match ($filter) {
            'live' => $query->currentlyRunning(),
            'upcoming' => $query->whereNull('cancelled_at')->where('ends_at', '>', $now)
                ->where(fn ($q) => $q->where('review_status', Ad::PENDING)
                    ->orWhere(fn ($q) => $q->where('review_status', Ad::APPROVED)->where('starts_at', '>', $now))),
            'past' => $query->where(fn ($q) => $q->whereNotNull('cancelled_at')->orWhere('review_status', Ad::REJECTED)->orWhere('ends_at', '<=', $now)),
            default => $query,
        };
    }

    private function row(Ad $ad, ?array $stats): array
    {
        $state = $ad->state();

        return [
            'id' => $ad->id,
            'name' => $ad->name,
            'thumb' => media_url($ad->image),
            'placement' => $ad->placement,
            'placement_label' => Ad::PLACEMENTS[$ad->placement] ?? $ad->placement,
            'state' => $state,
            'starts_at' => $ad->starts_at?->toIso8601String(),
            'ends_at' => $ad->ends_at?->toIso8601String(),
            'duration_label' => $ad->duration_hours ? Plan::adDurationLabel($ad->duration_hours) : null,
            'paid_with' => $ad->paid_with,
            'credits' => $ad->credits,
            'review_note' => $ad->review_note,
            'link' => match ($ad->link_type) {
                Ad::LINK_PROPERTY => ['type' => 'property', 'label' => $ad->property ? ($ad->property->getTranslation('title') ?: $ad->property->reference_no) : 'Listing removed'],
                Ad::LINK_PROFILE => ['type' => 'profile', 'label' => 'Your profile page'],
                default => ['type' => 'url', 'label' => $ad->link_url],
            },
            'stats' => $stats ?? ['impressions' => 0, 'clicks' => 0, 'ctr' => 0],
            'editable' => in_array($state, ['pending', 'scheduled'], true),
            'cancellable' => in_array($state, ['pending', 'scheduled', 'live', 'off'], true),
        ];
    }

    private function rules(bool $creating): array
    {
        $max = (int) (config('cms-kit.images.ads.image.max_size') ?? 4096);

        return [
            'name' => 'required|string|max:120',
            'link_type' => 'required|in:property,profile,url',
            'property_id' => 'nullable|required_if:link_type,property|integer',
            'link_url' => 'nullable|required_if:link_type,url|string|max:500',
            'image' => ($creating ? 'required' : 'nullable') . '|image|max:' . $max,
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

    /** Name, link and banner text from the request (text per language, blanks dropped; null = image only). */
    private function input(Request $request): array
    {
        $text = collect($request->input('translations', []))
            ->map(fn ($fields) => array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : null, (array) $fields), fn ($v) => $v !== null && $v !== ''))
            ->filter()->all();

        return $request->only(['name', 'link_type', 'property_id', 'link_url', 'image_alt']) + ['translations' => $text ?: null];
    }
}
