<?php

namespace App\Services;

use App\Models\AdPlacement;
use App\Models\AdStat;
use App\Models\CmsKit\Ad;
use App\Models\Plan;
use App\Models\PortalUser;
use App\Models\Property;
use App\Notifications\AdReviewNotification;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Client advertising — CRM › Advertisements. An agency / independent agent whose plan includes ads
 * books a banner on one page of the website for a few hours or days:
 *
 *  - Plan: ad_max_hours is the longest booking (e.g. 6 hours on the first paid plan, 1 day on the
 *    next, 2–3 days on the top ones; 0 = no advertising). ads_per_month are included; after that
 *    each booking is paid with credits (AdPlacement::cost()).
 *  - Slots: each page runs at most AdPlacement::max_slots client ads at the same time, counted hour
 *    by hour (bookings start on the hour and last whole hours, so that's exact).
 *  - Review: a booking is `pending` until an admin approves it (CMS › Ads › Client Ads). A rejected
 *    one, one cancelled before it ran, or one still unreviewed when its time is over gives its
 *    credits / allowance back. Stopping a live ad just ends it early.
 *
 * Like the plan and credits, ads belong to the account that owns the listings (an agency agent's
 * agency books them). Times are entered and shown in Dubai time; stored in UTC.
 */
class AdBookingService
{
    public const TIMEZONE = 'Asia/Dubai';

    /** Booking lengths offered, in hours (each plan only gets those up to its ad_max_hours). */
    public const DURATIONS = [1, 2, 3, 6, 12, 24, 48, 72];

    /** How far ahead an ad can be booked. */
    public const MAX_DAYS_AHEAD = 60;

    public function __construct(private readonly CreditService $credits)
    {
    }

    /** Whether $user books ads themselves (not an agency agent — their agency does). */
    public static function usableBy(?PortalUser $user): bool
    {
        return $user && $user->isApproved() && $user->listingOwner()->id === $user->id;
    }

    /** The account's plan when it is active, else null. */
    public function plan(PortalUser $account): ?Plan
    {
        $plan = $account->plan;

        return $plan?->status ? $plan : null;
    }

    public function includesAds(?PortalUser $account): bool
    {
        return (bool) ($account && $this->plan($account)?->includesAds());
    }

    /** Booking lengths (hours) $account's plan allows. */
    public function durations(PortalUser $account): array
    {
        $max = (int) $this->plan($account)?->ad_max_hours;

        return array_values(array_filter(self::DURATIONS, fn ($h) => $h <= $max));
    }

    /** Plan-paid bookings that start in $month (Dubai) and still hold the allowance. */
    public function usedInMonth(PortalUser $account, CarbonInterface $month): int
    {
        $local = Carbon::parse($month)->timezone(self::TIMEZONE);

        return Ad::where('portal_user_id', $account->id)->booked()->where('paid_with', 'plan')
            ->where('starts_at', '>=', $local->copy()->startOfMonth()->utc())
            ->where('starts_at', '<=', $local->copy()->endOfMonth()->utc())
            ->count();
    }

    /** ['included', 'limit', 'used', 'remaining', 'max_hours', 'max_label', 'durations', 'credits', 'balance', 'resets'] */
    public function quota(PortalUser $account): array
    {
        $plan = $this->plan($account);
        $limit = (int) $plan?->ads_per_month;
        $used = $this->usedInMonth($account, now());
        $credits = CreditService::usableBy($account);

        return [
            'included' => (bool) $plan?->includesAds(),
            'plan' => $plan?->getTranslation('name'),
            'limit' => $limit,
            'used' => $used,
            // Booked this month with credits instead — they don't use the plan's ads.
            'credit_ads' => Ad::where('portal_user_id', $account->id)->booked()->where('paid_with', 'credits')
                ->where('starts_at', '>=', now(self::TIMEZONE)->startOfMonth()->utc())
                ->where('starts_at', '<=', now(self::TIMEZONE)->endOfMonth()->utc())->count(),
            'remaining' => max(0, $limit - $used),
            'max_hours' => (int) $plan?->ad_max_hours,
            'max_label' => $plan?->includesAds() ? Plan::adDurationLabel((int) $plan->ad_max_hours) : null,
            'durations' => $this->durations($account),
            'credits' => $credits,
            'balance' => $credits ? $this->credits->balance($account) : 0,
            'resets' => now(self::TIMEZONE)->addMonthNoOverflow()->startOfMonth()->format('d M'),
        ];
    }

    /**
     * Client slots per hour of a Dubai day on $placement: [{hour, starts_at, booked, free, past}].
     * $ignore: an ad whose own booking shouldn't count (e.g. while editing it).
     */
    public function availability(AdPlacement $placement, string $date, ?int $ignore = null): array
    {
        $day = Carbon::createFromFormat('Y-m-d', $date, self::TIMEZONE)->startOfDay();
        $bookings = Ad::client()->booked()->forPlacement($placement->placement)
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore))
            ->overlapping($day->copy()->utc(), $day->copy()->addDay()->utc())
            ->get(['starts_at', 'ends_at']);
        $earliest = $this->earliestStart();

        return collect(range(0, 23))->map(function (int $hour) use ($day, $bookings, $placement, $earliest) {
            $from = $day->copy()->setHour($hour);
            $until = $from->copy()->addHour();
            $booked = $bookings->filter(fn ($b) => $b->starts_at->lt($until) && $b->ends_at->gt($from))->count();

            return [
                'hour' => $hour,
                'starts_at' => $from->toIso8601String(),
                'booked' => $booked,
                'free' => max(0, $placement->max_slots - $booked),
                'past' => $from->lt($earliest),
            ];
        })->all();
    }

    /** The first hour a new booking can start: the next full hour. */
    public function earliestStart(): Carbon
    {
        return now(self::TIMEZONE)->addHour()->startOfHour();
    }

    /**
     * Books an ad for $actor's account. $data: name, placement, start ("Y-m-d H:i", Dubai), hours,
     * pay_with (plan | credits), link_type + property_id / link_url, image_alt, translations.
     */
    public function book(PortalUser $actor, array $data, UploadedFile $image, ?UploadedFile $mobileImage = null): Ad
    {
        $account = $actor->listingOwner();
        abort_unless(self::usableBy($actor), 403, 'Advertising is managed by your agency.');

        $plan = $this->plan($account);
        if (!$plan?->includesAds()) {
            throw ValidationException::withMessages(['placement' => 'Website advertising isn\'t included in your plan. Upgrade to advertise.']);
        }

        $hours = (int) $data['hours'];
        if (!in_array($hours, $this->durations($account), true)) {
            throw ValidationException::withMessages(['hours' => 'Your ' . $plan->getTranslation('name') . ' plan allows ads of up to ' . Plan::adDurationLabel((int) $plan->ad_max_hours) . '.']);
        }

        $placement = $this->bookablePlacement($data['placement']);
        [$start, $end] = $this->window($data['start'], $hours);
        $link = $this->linkFields($account, $data);

        $payWith = ($data['pay_with'] ?? 'plan') === 'credits' ? 'credits' : 'plan';
        $cost = $placement->cost($hours);
        $this->assertCanPay($account, $plan, $payWith, $start);

        $files = app(ManagedFiles::class);
        $imagePath = $files->store($image, 'ads');
        $mobilePath = $mobileImage ? $files->store($mobileImage, 'ads') : null;

        return DB::transaction(function () use ($actor, $account, $plan, $data, $placement, $start, $end, $hours, $link, $payWith, $cost, $imagePath, $mobilePath) {
            // One booking at a time per account (its plan allowance) and per page (its last slot).
            PortalUser::whereKey($account->id)->lockForUpdate()->first();
            AdPlacement::whereKey($placement->id)->lockForUpdate()->first();
            $this->assertCanPay($account, $plan, $payWith, $start);
            $this->assertSlotsFree($placement, $start, $end);

            $ad = Ad::create([
                'portal_user_id' => $account->id,
                'name' => $data['name'],
                'image' => $imagePath,
                'mobile_image' => $mobilePath,
                'image_alt' => $data['image_alt'] ?? null,
                'translations' => $data['translations'] ?? null,
                'placement' => $placement->placement,
                'starts_at' => $start,
                'ends_at' => $end,
                'duration_hours' => $hours,
                'paid_with' => $payWith,
                'credits' => $payWith === 'credits' ? $cost : 0,
                'status' => true,
                'review_status' => Ad::PENDING,
                'order_index' => 0,
            ] + $link);

            if ($payWith === 'credits') {
                $spend = $this->credits->spend($actor, $cost, 'ad', 'Advertisement: ' . $placement->label() . ', '
                    . $start->copy()->timezone(self::TIMEZONE)->format('d M H:i') . ' (' . Plan::adDurationLabel($hours) . ')');
                $ad->update(['credit_transaction_id' => $spend->id]);
            }

            return $ad;
        });
    }

    /**
     * Changes an ad's banner / text / link before it starts. The booking (page, time, payment)
     * stays; an approved ad goes back to review because what's shown changed.
     */
    public function updateCreative(Ad $ad, array $data, ?UploadedFile $image = null, ?UploadedFile $mobileImage = null, bool $removeMobile = false): Ad
    {
        if (!in_array($ad->state(), ['pending', 'scheduled'], true)) {
            throw ValidationException::withMessages(['name' => 'Only an ad that hasn\'t started yet can be changed.']);
        }

        $files = app(ManagedFiles::class);
        $fields = [
            'name' => $data['name'],
            'image_alt' => $data['image_alt'] ?? null,
            'translations' => $data['translations'] ?? null,
            'review_status' => Ad::PENDING,
            'review_note' => null,
            'reviewed_at' => null,
            'reviewed_by' => null,
        ] + $this->linkFields($ad->owner, $data);

        if ($image) {
            $files->delete(Ad::ownFile($ad->image));
            $fields['image'] = $files->store($image, 'ads');
        }
        if ($mobileImage || $removeMobile) {
            $files->delete(Ad::ownFile($ad->mobile_image));
            $fields['mobile_image'] = $mobileImage ? $files->store($mobileImage, 'ads') : null;
        }

        $ad->update($fields);

        return $ad;
    }

    /**
     * Cancel / stop. An ad that never ran (still waiting for review, or not started yet) is cancelled
     * and gives its credits / allowance back; a live one stops now (its time was used, so no refund).
     */
    public function cancel(Ad $ad): string
    {
        $state = $ad->state();
        if (!in_array($state, ['pending', 'scheduled', 'live', 'off'], true)) {
            throw ValidationException::withMessages(['ad' => 'This ad has already finished.']);
        }

        return DB::transaction(function () use ($ad, $state) {
            if ($state === 'live' || ($state === 'off' && $ad->starts_at?->lte(now()))) {
                $ad->update(['ends_at' => now()]);

                return 'stopped';
            }

            $ad->update(['cancelled_at' => now()]);
            $this->refund($ad, 'Advertisement cancelled');

            return 'cancelled';
        });
    }

    public function approve(Ad $ad, string $adminName): void
    {
        if ($ad->review_status !== Ad::PENDING || $ad->cancelled_at) {
            throw ValidationException::withMessages(['ad' => 'Only an ad waiting for review can be approved.']);
        }
        if ($ad->ends_at && $ad->ends_at->lte(now())) {
            throw ValidationException::withMessages(['ad' => 'Its booked time is already over — reject it so the client gets their credits back.']);
        }

        $ad->update(['review_status' => Ad::APPROVED, 'review_note' => null, 'reviewed_at' => now(), 'reviewed_by' => $adminName]);
        $ad->owner?->notify(new AdReviewNotification($ad, 'approved'));
    }

    public function reject(Ad $ad, string $note, string $adminName): void
    {
        if (!$ad->isClientAd() || $ad->cancelled_at || $ad->review_status === Ad::REJECTED || ($ad->ends_at && $ad->ends_at->lte(now()))) {
            throw ValidationException::withMessages(['ad' => 'This ad can no longer be rejected.']);
        }

        DB::transaction(function () use ($ad, $note, $adminName) {
            $ran = $ad->review_status === Ad::APPROVED && $ad->starts_at?->lte(now());
            $ad->update(['review_status' => Ad::REJECTED, 'review_note' => $note, 'reviewed_at' => now(), 'reviewed_by' => $adminName]
                + ($ran ? ['ends_at' => now()] : []));
            // Taken down after it already ran a while: the time used isn't refunded.
            if (!$ran) {
                $this->refund($ad, 'Advertisement not approved');
            }
        });
        $ad->owner?->notify(new AdReviewNotification($ad, 'rejected', $note));
    }

    /**
     * Ads whose time ran out while still waiting for review are closed as not reviewed, and their
     * credits / allowance given back (scheduled `ads:settle`).
     */
    public function settle(): int
    {
        $count = 0;
        Ad::client()->where('review_status', Ad::PENDING)->whereNull('cancelled_at')->where('ends_at', '<=', now())
            ->each(function (Ad $ad) use (&$count) {
                DB::transaction(function () use ($ad) {
                    $ad->update(['review_status' => Ad::REJECTED, 'review_note' => 'It wasn\'t reviewed before its booked time ended.', 'reviewed_at' => now(), 'reviewed_by' => 'System']);
                    $this->refund($ad, 'Advertisement not reviewed in time');
                });
                $ad->owner?->notify(new AdReviewNotification($ad, 'expired'));
                $count++;
            });

        return $count;
    }

    // ------------------------------------------------------------------ reports

    /** Impressions / clicks / CTR for a set of ads (or every ad of an account) since $from. */
    public function totals(iterable|int $adIds, ?CarbonInterface $from = null): array
    {
        $row = AdStat::whereIn('ad_id', is_int($adIds) ? [$adIds] : collect($adIds)->all())
            ->when($from, fn ($q) => $q->where('date', '>=', $from->copy()->utc()->toDateString()))
            ->selectRaw('COALESCE(SUM(impressions), 0) AS impressions, COALESCE(SUM(clicks), 0) AS clicks')
            ->first();

        return $this->kpis((int) $row->impressions, (int) $row->clicks);
    }

    /** Per-ad totals keyed by id: [id => [impressions, clicks, ctr]]. */
    public function totalsByAd(iterable $adIds): array
    {
        return AdStat::whereIn('ad_id', collect($adIds)->all())
            ->groupBy('ad_id')
            ->selectRaw('ad_id, SUM(impressions) AS impressions, SUM(clicks) AS clicks')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->ad_id => $this->kpis((int) $row->impressions, (int) $row->clicks)])
            ->all();
    }

    /**
     * One ad's report: totals, an hourly series over its run (Dubai time), the totals per day, and
     * the busiest hours of the day.
     */
    public function report(Ad $ad): array
    {
        $tz = self::TIMEZONE;
        $rows = AdStat::where('ad_id', $ad->id)->orderBy('date')->orderBy('hour')->get()
            ->map(fn (AdStat $s) => [
                'at' => Carbon::parse($s->date->toDateString() . ' ' . str_pad((string) $s->hour, 2, '0', STR_PAD_LEFT) . ':00:00', 'UTC')->timezone($tz),
                'impressions' => $s->impressions,
                'clicks' => $s->clicks,
            ]);

        $from = ($ad->starts_at ?? $rows->first()['at'] ?? now())->copy()->timezone($tz)->startOfHour();
        $until = ($ad->ends_at && $ad->ends_at->lt(now()) ? $ad->ends_at : now())->copy()->timezone($tz);
        if ($until->lt($from)) {
            $until = $from->copy();
        }
        // Long-running (MW Realty) ads: hourly for the last 72 hours only.
        if ($from->diffInHours($until) > 72) {
            $from = $until->copy()->subHours(72)->startOfHour();
        }

        $byHour = $rows->keyBy(fn ($r) => $r['at']->format('Y-m-d H'));
        $hourly = [];
        for ($at = $from->copy(); $at->lte($until); $at->addHour()) {
            $hit = $byHour->get($at->format('Y-m-d H'));
            $hourly[] = ['at' => $at->toIso8601String(), 'impressions' => $hit['impressions'] ?? 0, 'clicks' => $hit['clicks'] ?? 0];
        }

        $daily = $rows->groupBy(fn ($r) => $r['at']->toDateString())
            ->map(fn ($day, $date) => ['date' => $date] + $this->kpis($day->sum('impressions'), $day->sum('clicks')))
            ->values()->all();

        $hoursOfDay = $rows->groupBy(fn ($r) => $r['at']->hour)
            ->map(fn ($hour, $h) => ['hour' => (int) $h, 'impressions' => $hour->sum('impressions'), 'clicks' => $hour->sum('clicks')])
            ->sortByDesc('impressions')->take(5)->values()->all();

        return [
            'totals' => $this->kpis($rows->sum('impressions'), $rows->sum('clicks')),
            'hourly' => $hourly,
            'daily' => $daily,
            'top_hours' => $hoursOfDay,
            'updated_at' => AdStat::where('ad_id', $ad->id)->max('updated_at'),
        ];
    }

    private function kpis(int $impressions, int $clicks): array
    {
        return [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => $impressions > 0 ? round($clicks / $impressions * 100, 2) : 0.0,
        ];
    }

    // ------------------------------------------------------------------ helpers

    private function bookablePlacement(string $key): AdPlacement
    {
        $placement = AdPlacement::for($key);
        if (!$placement?->client_bookable || $placement->max_slots < 1) {
            throw ValidationException::withMessages(['placement' => 'Ads can\'t be booked on this page.']);
        }

        return $placement;
    }

    /** [start, end] in UTC for a Dubai "Y-m-d H:i" start and a length in hours. */
    private function window(string $start, int $hours): array
    {
        try {
            $from = Carbon::createFromFormat('Y-m-d H:i', $start, self::TIMEZONE)->startOfHour();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['start' => 'Pick a start date and hour.']);
        }
        if ($from->lt($this->earliestStart())) {
            throw ValidationException::withMessages(['start' => 'Pick a start from ' . $this->earliestStart()->format('d M, H:i') . ' onwards.']);
        }
        if ($from->gt(now(self::TIMEZONE)->addDays(self::MAX_DAYS_AHEAD))) {
            throw ValidationException::withMessages(['start' => 'Ads can be booked up to ' . self::MAX_DAYS_AHEAD . ' days ahead.']);
        }

        return [$from->copy()->utc(), $from->copy()->addHours($hours)->utc()];
    }

    /** 422 when the month's plan ads are used up (pay_with=plan) or the account can't use credits. */
    private function assertCanPay(PortalUser $account, Plan $plan, string $payWith, Carbon $start): void
    {
        if ($payWith === 'plan' && $this->usedInMonth($account, $start) >= (int) $plan->ads_per_month) {
            throw ValidationException::withMessages(['pay_with' => (int) $plan->ads_per_month > 0
                ? "You've used all {$plan->ads_per_month} ads included for " . $start->copy()->timezone(self::TIMEZONE)->format('F') . '. Pay with credits instead.'
                : 'Your plan doesn\'t include free ads — pay with credits.']);
        }
        if ($payWith === 'credits' && !CreditService::usableBy($account)) {
            throw ValidationException::withMessages(['pay_with' => 'Credits aren\'t available on your account.']);
        }
    }

    /** 422 naming the first hour of [start, end) where the page has no client slot left. */
    private function assertSlotsFree(AdPlacement $placement, Carbon $start, Carbon $end, ?int $ignore = null): void
    {
        $bookings = Ad::client()->booked()->forPlacement($placement->placement)
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore))
            ->overlapping($start, $end)->get(['starts_at', 'ends_at']);

        for ($at = $start->copy(); $at->lt($end); $at->addHour()) {
            $until = $at->copy()->addHour();
            if ($bookings->filter(fn ($b) => $b->starts_at->lt($until) && $b->ends_at->gt($at))->count() >= $placement->max_slots) {
                throw ValidationException::withMessages(['start' => $placement->label() . ' is fully booked at '
                    . $at->copy()->timezone(self::TIMEZONE)->format('d M, H:i') . '. Pick another time or page.']);
            }
        }
    }

    /** link_type + link_url / property_id, validated against what $account may link to. */
    private function linkFields(PortalUser $account, array $data): array
    {
        $type = $data['link_type'] ?? Ad::LINK_URL;

        if ($type === Ad::LINK_PROPERTY) {
            $property = Property::where('portal_user_id', $account->id)->where('status', true)->find((int) ($data['property_id'] ?? 0));
            if (!$property) {
                throw ValidationException::withMessages(['property_id' => 'Pick one of your active listings.']);
            }

            return ['link_type' => Ad::LINK_PROPERTY, 'property_id' => $property->id, 'link_url' => null];
        }

        if ($type === Ad::LINK_PROFILE) {
            return ['link_type' => Ad::LINK_PROFILE, 'property_id' => null, 'link_url' => null];
        }

        $url = trim((string) ($data['link_url'] ?? ''));
        if (!preg_match('#^https?://[^\s]+$#i', $url)) {
            throw ValidationException::withMessages(['link_url' => 'Enter a full web address starting with https://']);
        }

        return ['link_type' => Ad::LINK_URL, 'property_id' => null, 'link_url' => $url];
    }

    private function refund(Ad $ad, string $description): void
    {
        if ($ad->paid_with === 'credits' && $ad->creditTransaction) {
            $this->credits->refund($ad->creditTransaction, $description);
        }
    }
}
