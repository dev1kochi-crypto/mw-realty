<?php

namespace App\Services;

use App\Models\PortalUser;
use App\Models\Property;
use App\Models\PropertyFeaturing;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Plan-based "Featured listing" quota: N listings featured at a time, or N featurings per calendar
 * month (plans.featured_period), each running for up to featured_max_days. Expiry is applied by
 * the `properties:expire-featured` scheduled command.
 *
 * Super Admin featuring (property form's Featured switch) bypasses all of this and never expires.
 */
class FeaturedListingService
{
    /**
     * ['limit', 'used', 'remaining', 'max_days', 'per_month'] for the owner's plan. A per-month
     * plan counts featurings started this calendar month (stopping early doesn't refund it); any
     * other plan counts listings featured right now, so a slot frees up as soon as one ends.
     */
    public function quota(PortalUser $owner): array
    {
        $plan = $owner->plan?->status ? $owner->plan : null;
        $limit = (int) ($plan?->featured_per_month ?? 0);
        $perMonth = (bool) $plan?->featuredPerMonth();

        $used = $perMonth
            ? $owner->featurings()->thisMonth()->count()
            : $owner->featurings()->whereNull('stopped_at')->where('ends_at', '>', now())->count();

        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
            'max_days' => $plan?->featured_max_days,
            'per_month' => $perMonth,
        ];
    }

    public function feature(PortalUser $owner, Property $property, int $days): PropertyFeaturing
    {
        $quota = $this->quota($owner);

        if ($quota['limit'] === 0) {
            throw ValidationException::withMessages(['days' => 'Featured listings are not included in your plan. Upgrade to feature your listings.']);
        }
        if ($property->featured) {
            throw ValidationException::withMessages(['days' => 'This listing is already featured.']);
        }
        if (!$property->status) {
            throw ValidationException::withMessages(['days' => 'Only active listings can be featured.']);
        }
        if ($quota['remaining'] === 0) {
            throw ValidationException::withMessages(['days' => $quota['per_month']
                ? "You've used all {$quota['limit']} featured listing(s) for this month. Your quota resets on " . now()->addMonthNoOverflow()->startOfMonth()->format('d M') . '.'
                : "Your plan allows {$quota['limit']} featured listing(s) at a time. Stop one, or wait for one to end, to feature another."]);
        }
        if ($days < 1 || ($quota['max_days'] && $days > $quota['max_days'])) {
            throw ValidationException::withMessages(['days' => 'Choose between 1 and ' . ($quota['max_days'] ?? 365) . ' days.']);
        }

        return DB::transaction(function () use ($owner, $property, $days) {
            $endsAt = now()->addDays($days);
            $property->update(['featured' => true, 'featured_until' => $endsAt]);

            return $owner->featurings()->create([
                'property_id' => $property->id,
                'starts_at' => now(),
                'ends_at' => $endsAt,
            ]);
        });
    }

    /** Stops a feature early. The slot is still counted for this month. */
    public function stop(Property $property): void
    {
        DB::transaction(function () use ($property) {
            PropertyFeaturing::where('property_id', $property->id)->whereNull('stopped_at')->where('ends_at', '>', now())
                ->update(['stopped_at' => now()]);
            $property->update(['featured' => false, 'featured_until' => null]);
        });
    }

    /** Un-features every listing whose plan-based feature has run out. Returns how many. */
    public function expire(): int
    {
        return Property::where('featured', true)
            ->whereNotNull('featured_until')
            ->where('featured_until', '<=', now())
            ->update(['featured' => false, 'featured_until' => null]);
    }
}
