<?php

namespace App\Services;

use App\Models\PortalUser;
use App\Models\Property;
use App\Models\PropertyFeaturing;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Plan-based "Featured listing" quota: N listings featured at a time, or N featurings per calendar
 * month (plans.featured_period), each running for up to featured_max_days.
 *
 * A feature is booked for a start and end date. A start in the future is "scheduled": the property
 * keeps featured = false with featured_from set, and sync() (run by the `properties:expire-featured`
 * scheduled command, and on the CRM listing pages) switches it live on that day and back off after
 * the end date.
 *
 * Super Admin featuring bypasses the plan quota, and may leave the end date open.
 */
class FeaturedListingService
{
    /** Longest feature a Super Admin can book with an end date, and the cap for plans with no max. */
    public const MAX_DAYS = 365;

    /**
     * ['limit', 'used', 'remaining', 'max_days', 'per_month'] for the owner's plan. A per-month
     * plan counts featurings started this calendar month (stopping early doesn't refund it); any
     * other plan counts listings featured or scheduled right now, so a slot frees up as soon as
     * one ends.
     */
    public function quota(PortalUser $owner): array
    {
        // An agency agent uses the agency's plan, and the agency + its agents share one quota —
        // otherwise every agent would multiply the agency's premium allowance.
        $plan = $owner->effectivePlan();
        $plan = $plan?->status ? $plan : null;
        $limit = (int) ($plan?->featured_per_month ?? 0);
        $perMonth = (bool) $plan?->featuredPerMonth();

        $account = $owner->isOnAgencyPlan() ? $owner->company : $owner;
        $bookers = $account->isAgency()
            ? PortalUser::where('company_id', $account->id)->with('currentMembership')->get()
                ->filter(fn (PortalUser $agent) => $agent->isOnAgencyPlan())->pluck('id')->push($account->id)
            : collect([$owner->id]);
        $bookings = fn () => \App\Models\PropertyFeaturing::whereIn('portal_user_id', $bookers);

        $used = $perMonth
            ? $bookings()->thisMonth()->count()
            : $bookings()->whereNull('stopped_at')->where('ends_at', '>', now())->count();

        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
            'max_days' => $plan?->featured_max_days,
            'per_month' => $perMonth,
        ];
    }

    /** Books a plan-quota feature for an agent/company listing. */
    public function feature(PortalUser $owner, Property $property, ?string $startDate, ?string $endDate): PropertyFeaturing
    {
        $quota = $this->quota($owner);

        if ($quota['limit'] === 0) {
            throw ValidationException::withMessages(['start_date' => 'Premium listings are not included in your plan. Upgrade to make your listings.']);
        }
        $this->assertCanFeature($property);

        [$start, $end] = $this->period($startDate, $endDate, true, $quota['max_days'] ?: self::MAX_DAYS);

        if ($quota['per_month']) {
            $usedThatMonth = $owner->featurings()
                ->whereBetween('starts_at', [$start->copy()->startOfMonth(), $start->copy()->endOfMonth()])
                ->count();
            if ($usedThatMonth >= $quota['limit']) {
                throw ValidationException::withMessages(['start_date' => "You've used all {$quota['limit']} premium listing(s) for " . $start->format('F')
                    . '. Pick a start date in another month' . ($start->isSameMonth(now()) ? ' — your quota resets on ' . now()->addMonthNoOverflow()->startOfMonth()->format('d M') . '.' : '.')]);
            }
        } else {
            // Features that overlap the requested period — a conservative "at a time" check that
            // also counts ones scheduled to start later.
            $overlapping = $owner->featurings()
                ->whereNull('stopped_at')
                ->where('ends_at', '>', now())
                ->where('starts_at', '<', $end)
                ->where('ends_at', '>', $start)
                ->count();
            if ($overlapping >= $quota['limit']) {
                throw ValidationException::withMessages(['start_date' => "Your plan allows {$quota['limit']} premium listing(s) at a time and that period is already fully booked. Stop one, or pick dates after one ends."]);
            }
        }

        return DB::transaction(function () use ($owner, $property, $start, $end) {
            $this->applyToProperty($property, $start, $end);

            return $owner->featurings()->create([
                'property_id' => $property->id,
                'starts_at' => $start,
                'ends_at' => $end,
            ]);
        });
    }

    /**
     * Books the same dates for several listings at once (multi-select on the Featured menu).
     * All-or-nothing: each booking is checked against the quota including the ones before it in
     * this batch, and if any fails nothing is saved. $owner = null means Super Admin (no quota).
     * Errors name the listing that failed.
     *
     * @param  \Illuminate\Support\Collection<int, Property>  $properties
     */
    public function featureMany(?PortalUser $owner, $properties, ?string $startDate, ?string $endDate): int
    {
        if ($owner) {
            $quota = $this->quota($owner);
            // Early, clearer message than the per-listing one when the batch simply doesn't fit.
            if (!$quota['per_month'] && $quota['limit'] > 0 && $properties->count() > $quota['remaining']) {
                throw ValidationException::withMessages(['property_ids' => "You picked {$properties->count()} listings but your plan has {$quota['remaining']} premium slot(s) free right now."]);
            }
        }

        DB::transaction(function () use ($owner, $properties, $startDate, $endDate) {
            foreach ($properties as $property) {
                try {
                    $owner
                        ? $this->feature($owner, $property, $startDate, $endDate)
                        : $this->featureAsAdmin($property, $startDate, $endDate);
                } catch (ValidationException $e) {
                    $message = collect($e->errors())->flatten()->first();
                    throw ValidationException::withMessages(['property_ids' => ($property->getTranslation('title') ?: $property->reference_no) . ': ' . $message . ' Nothing was made premium.']);
                }
            }
        });

        return $properties->count();
    }

    /** Super Admin feature: no quota, and an empty end date means "until stopped". */
    public function featureAsAdmin(Property $property, ?string $startDate, ?string $endDate): void
    {
        $this->assertCanFeature($property, requireActive: false);
        [$start, $end] = $this->period($startDate, $endDate, false, self::MAX_DAYS);
        $this->applyToProperty($property, $start, $end);
    }

    /**
     * Which of these property ids the viewer may change feature dates on: all of them for Super
     * Admin ($owner = null); for an owner, only features they booked on their own plan.
     *
     * @return array<int, true> property id => true
     */
    public function editableIds(?PortalUser $owner, iterable $propertyIds): array
    {
        $ids = collect($propertyIds)->map(fn ($id) => (int) $id)->unique()->values();
        if (!$owner) {
            return $ids->mapWithKeys(fn ($id) => [$id => true])->all();
        }

        return PropertyFeaturing::whereIn('property_id', $ids)
            ->where('portal_user_id', $owner->id)
            ->whereNull('stopped_at')
            ->where('ends_at', '>', now())
            ->pluck('property_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /**
     * Changes the dates of a live or scheduled feature ("Edit dates" on the Featured menu).
     * A live feature keeps its start (it has already begun), so only the end date moves; a scheduled
     * one can move both. Owners stay within their plan (max days, and the quota counted without
     * this booking); a Super Admin ($owner = null) has no quota and may leave the end open.
     */
    public function reschedule(Property $property, ?PortalUser $owner, ?string $startDate, ?string $endDate): void
    {
        $live = (bool) $property->featured;
        if (!$live && !$property->isFeatureScheduled()) {
            throw ValidationException::withMessages(['start_date' => 'This listing isn\'t premium or scheduled any more.']);
        }

        $booking = PropertyFeaturing::where('property_id', $property->id)->whereNull('stopped_at')->where('ends_at', '>', now())
            ->latest('starts_at')->first();

        if ($owner) {
            // Features set by MW Realty (no plan booking) can't be changed by the owner.
            if (!$booking || $booking->portal_user_id !== $owner->id) {
                throw ValidationException::withMessages(['start_date' => 'This premium listing was set by MW Realty, so its dates can\'t be changed here.']);
            }
            $quota = $this->quota($owner);
            $maxDays = $quota['max_days'] ?: self::MAX_DAYS;
        } else {
            $maxDays = self::MAX_DAYS;
        }

        if ($live) {
            // Already running: the start stays; the new end must be today or later.
            $start = $property->featured_from ?? $booking?->starts_at ?? now();
            $endDay = $endDate || $owner ? $this->parseDate($endDate, 'end_date', 'Choose an end date.') : null;
            if ($endDay) {
                if ($endDay->lt(today())) {
                    throw ValidationException::withMessages(['end_date' => 'The end date can\'t be in the past.']);
                }
                $days = $start->copy()->startOfDay()->diffInDays($endDay) + 1;
                if ($days > $maxDays) {
                    throw ValidationException::withMessages(['end_date' => "Premium can run for at most {$maxDays} day(s) — counting from its start on " . $start->format('d M') . ", that's {$days}."]);
                }
            }
            $end = $endDay?->copy()->endOfDay();
        } else {
            [$start, $end] = $this->period($startDate, $endDate, (bool) $owner, $maxDays);
        }

        if ($owner) {
            $others = $owner->featurings()->where('id', '!=', $booking->id);
            if ($quota['per_month']) {
                $usedThatMonth = (clone $others)->whereBetween('starts_at', [$start->copy()->startOfMonth(), $start->copy()->endOfMonth()])->count();
                if ($usedThatMonth >= $quota['limit']) {
                    throw ValidationException::withMessages(['start_date' => "You've already used all {$quota['limit']} premium listing(s) for " . $start->format('F') . '. Pick a start date in another month.']);
                }
            } else {
                $overlapping = (clone $others)->whereNull('stopped_at')->where('ends_at', '>', now())
                    ->where('starts_at', '<', $end)->where('ends_at', '>', $start)->count();
                if ($overlapping >= $quota['limit']) {
                    throw ValidationException::withMessages(['end_date' => "Your plan allows {$quota['limit']} premium listing(s) at a time and those dates overlap another premium listing."]);
                }
            }
        }

        DB::transaction(function () use ($property, $booking, $start, $end) {
            $this->applyToProperty($property, $start, $end);
            // Admin bookings have no end (null); the booking row always needs one, so it's only
            // updated when there's an end date to store.
            if ($booking && $end) {
                $booking->update(['starts_at' => $start, 'ends_at' => $end]);
            }
        });
    }

    /**
     * Stops a live feature early (still counted for this month) or cancels a scheduled one (the
     * booking is removed, so it gives the slot back).
     */
    public function stop(Property $property): void
    {
        DB::transaction(function () use ($property) {
            $open = PropertyFeaturing::where('property_id', $property->id)->whereNull('stopped_at')->where('ends_at', '>', now());
            (clone $open)->where('starts_at', '>', now())->delete();
            $open->update(['stopped_at' => now()]);
            $property->update(['featured' => false, 'featured_from' => null, 'featured_until' => null]);
        });
    }

    /**
     * Switches scheduled features live once their start is reached, and un-features every
     * listing whose end date has passed. Returns how many listings changed.
     */
    public function sync(): int
    {
        $activated = Property::where('featured', false)
            ->whereNotNull('featured_from')
            ->where('featured_from', '<=', now())
            ->where(fn ($q) => $q->whereNull('featured_until')->orWhere('featured_until', '>', now()))
            ->update(['featured' => true]);

        $expired = Property::whereNotNull('featured_until')
            ->where('featured_until', '<=', now())
            ->where(fn ($q) => $q->where('featured', true)->orWhereNotNull('featured_from'))
            ->update(['featured' => false, 'featured_from' => null, 'featured_until' => null]);

        return $activated + $expired;
    }

    protected function assertCanFeature(Property $property, bool $requireActive = true): void
    {
        if ($property->featured) {
            throw ValidationException::withMessages(['start_date' => 'This listing is already premium.']);
        }
        if ($property->isFeatureScheduled()) {
            throw ValidationException::withMessages(['start_date' => 'This listing already has premium scheduled from ' . $property->featured_from->format('d M Y') . '. Cancel it first to book new dates.']);
        }
        if ($requireActive && !$property->status) {
            throw ValidationException::withMessages(['start_date' => 'Only active listings can be made premium.']);
        }
    }

    /**
     * Date inputs (Y-m-d) → [start, end]. The start is today or later (today means "right now");
     * the end runs to the end of its day. Both dates count, so 1 Oct → 7 Oct is 7 days.
     *
     * @return array{0: Carbon, 1: ?Carbon}
     */
    protected function period(?string $startDate, ?string $endDate, bool $endRequired, int $maxDays): array
    {
        $startDay = $this->parseDate($startDate, 'start_date', 'Choose a start date.');
        if ($startDay->lt(today())) {
            throw ValidationException::withMessages(['start_date' => 'The start date can\'t be in the past.']);
        }

        $endDay = null;
        if ($endDate || $endRequired) {
            $endDay = $this->parseDate($endDate, 'end_date', 'Choose an end date.');
            if ($endDay->lt($startDay)) {
                throw ValidationException::withMessages(['end_date' => 'The end date must be on or after the start date.']);
            }
            $days = $startDay->diffInDays($endDay) + 1;
            if ($days > $maxDays) {
                throw ValidationException::withMessages(['end_date' => "Premium can run for at most {$maxDays} day(s) — you picked {$days}."]);
            }
        }

        $start = $startDay->isToday() ? now() : $startDay->copy()->startOfDay();

        return [$start, $endDay?->copy()->endOfDay()];
    }

    protected function parseDate(?string $value, string $field, string $missing): Carbon
    {
        if (!$value) {
            throw ValidationException::withMessages([$field => $missing]);
        }
        try {
            return Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => 'Enter a valid date.']);
        }
    }

    protected function applyToProperty(Property $property, Carbon $start, ?Carbon $end): void
    {
        $property->update([
            'featured' => !$start->isFuture(),
            'featured_from' => $start,
            'featured_until' => $end,
        ]);
    }
}
