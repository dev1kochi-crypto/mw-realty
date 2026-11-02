<?php

namespace Database\Seeders;

use App\Models\ListingReview;
use App\Models\Property;
use App\Models\PropertyDetail;
use App\Services\ListingReview\ListingReviewService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Demo Listing Review cases (not part of DatabaseSeeder — LOCAL ONLY, run it on demand):
 *   php artisan db:seed --class=ListingReviewDemoSeeder
 *
 * Picks groups of 2–3 listings from DIFFERENT agencies in the same location (same sale / rent) and
 * gives each group one unit number — the same unit listed by several agencies — then runs the real
 * screening (ListingReviewService): the oldest listing stays live, the newer ones wait for review.
 * Also opens risk cases (price far off the area), and resolves a few (approved / rejected) so the
 * Resolved tab has examples.
 *
 * It RESETS all Listing Review data first, removes the unit numbers it set last time (marked in the
 * listing's metadata) and puts held listings back as they were. No notifications are sent and photos
 * aren't fingerprinted.
 */
class ListingReviewDemoSeeder extends Seeder
{
    private const UNIT_GROUPS = 6;   // 4 stay open, 1 approved, 1 rejected
    private const OPEN_RISKS = 4;    // + 1 approved

    public function run(ListingReviewService $reviews): void
    {
        Notification::fake();
        Bus::fake();

        $this->reset($reviews);

        // Same unit, several agencies.
        $groups = $this->unitGroups();
        foreach ($groups as $g => $group) {
            $unit = $g % 2 ? 'Villa ' . (20 + $g * 7) : (string) (1000 + $g * 101);
            foreach ($group as $property) {
                PropertyDetail::updateOrCreate(['property_id' => $property->id], ['unit_number' => $unit]);
                $property->update(['metadata' => array_merge((array) $property->metadata, ['demo_unit_number' => true])]);
            }
            // The newer listings are the ones "being added" — screening them opens the case.
            foreach ($group->slice(1) as $property) {
                $reviews->screen($property->refresh());
            }
        }

        // Risk cases.
        $risks = fn () => ListingReview::open()->where('type', ListingReview::RISK)->count();
        Property::query()->available()->where('review_hold', false)->orderBy('id')
            ->each(function (Property $property) use ($reviews, $risks) {
                if ($risks() >= self::OPEN_RISKS + 1) {
                    return false;
                }
                $reviews->screen($property);
            });

        // A few decided, for the Resolved tab.
        $cases = ListingReview::open()->where('type', ListingReview::DUPLICATE)->latest('id')->take(2)->get();
        if ($approve = $cases->get(0)) {
            foreach ($this->waiting($approve) as $p) {
                $reviews->approve($approve, $p->id, 'Both agencies have their own DLD permit for this unit — non-exclusive listing.');
            }
        }
        if ($reject = $cases->get(1)) {
            foreach ($this->waiting($reject) as $p) {
                $reviews->reject($reject, $p->id, 'The owner confirmed only the first agency has authority (Form A) to market this unit.');
            }
        }
        if ($risk = ListingReview::open()->where('type', ListingReview::RISK)->latest('id')->first()) {
            foreach ($this->waiting($risk) as $p) {
                $reviews->approve($risk, $p->id, 'Checked with the agency: distressed sale, price confirmed by the owner.');
            }
        }

        $this->command?->info(sprintf('Listing Review: %d same-unit cases, %d risk cases open; %d resolved.',
            ListingReview::open()->where('type', ListingReview::DUPLICATE)->count(), $risks(),
            ListingReview::where('status', ListingReview::RESOLVED)->count()));
    }

    /**
     * Groups of 2–3 available listings: same location and sale / rent, each from a different agency,
     * oldest first. The first two have a listing still waiting on Listing Permits as the newer copy —
     * so Listing Permits shows a duplicate too.
     */
    private function unitGroups(): array
    {
        $groups = [];
        $used = [];

        $pending = Property::query()->available()->where('compliance_status', Property::COMPLIANCE_PENDING)
            ->where('review_hold', false)->whereNotNull('location')->orderByDesc('created_at')->get();
        foreach ($pending as $newer) {
            $older = Property::query()->available()->where('review_hold', false)
                ->where('location', $newer->location)->where('listing_type', $newer->listing_type)
                ->where('created_at', '<', $newer->created_at)
                ->where(fn ($q) => $q->whereNull('portal_user_id')->orWhere('portal_user_id', '!=', $newer->portal_user_id))
                ->whereNotIn('id', array_keys($used))
                ->orderBy('created_at')->first();
            if ($older) {
                $used[$older->id] = $used[$newer->id] = true;
                $groups[] = collect([$older, $newer]);
            }
            if (count($groups) >= 2) {
                break;
            }
        }
        $buckets = Property::query()->available()->where('review_hold', false)->whereNotNull('location')
            ->orderBy('created_at')->orderBy('id')
            ->get(['id', 'portal_user_id', 'location', 'listing_type', 'created_at', 'metadata'])
            ->groupBy(fn ($p) => $p->location . '|' . $p->listing_type);

        foreach ($buckets as $bucket) {
            $group = $bucket->reject(fn ($p) => isset($used[$p->id]))->unique('portal_user_id')->take(count($groups) % 2 ? 2 : 3)->values();
            if ($group->count() < 2) {
                continue;
            }
            $group->each(function ($p) use (&$used) {
                $used[$p->id] = true;
            });
            $groups[] = $group;
            if (count($groups) >= self::UNIT_GROUPS) {
                break;
            }
        }

        return $groups;
    }

    private function waiting(ListingReview $review)
    {
        // A risk case's listing waits even while it stays live; in a same-unit case only the held copies do.
        $query = $review->properties()->wherePivotNull('outcome');
        if ($review->type === ListingReview::DUPLICATE) {
            $query->wherePivot('held', true);
        }

        return $query->get();
    }

    private function reset(ListingReviewService $reviews): void
    {
        $held = Property::where('review_hold', true)->get();
        DB::transaction(function () {
            ListingReview::query()->delete();
            DB::table('listing_distinct_pairs')->delete();
            Property::whereNotNull('metadata->review_rejection')->orWhereNotNull('metadata->review_resubmitted')->orWhereNotNull('metadata->review_cleared_signature')
                ->get()->each(function (Property $p) {
                    $p->update(['metadata' => array_diff_key((array) $p->metadata, array_flip(['review_rejection', 'review_resubmitted', 'review_cleared_signature']))]);
                });
            Property::where('metadata->demo_unit_number', true)->get()->each(function (Property $p) {
                PropertyDetail::where('property_id', $p->id)->update(['unit_number' => null]);
                $p->update(['metadata' => array_diff_key((array) $p->metadata, ['demo_unit_number' => true])]);
            });
        });
        // No open case or rejection holds them any more → back on / off as before the review.
        $held->each(fn (Property $p) => $reviews->syncHold($p->refresh(), quiet: true));
    }
}
