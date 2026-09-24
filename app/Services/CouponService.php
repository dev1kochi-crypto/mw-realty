<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Plan;
use App\Models\PlanUpgradeRequest;
use App\Models\PortalUser;
use Illuminate\Validation\ValidationException;

/**
 * Coupon lifecycle for plan upgrades:
 *  1. check()  — Agent/Company enters a code on the portal Plans page (preview + on submit).
 *  2. redeem() — Super Admin approves the plan request; the code is re-checked and recorded.
 *  3. applyToPayment() — each payment recorded for that plan gets the discount while the
 *     redemption still has payments left (once / N months / forever).
 */
class CouponService
{
    /**
     * Validates $code for $owner on $plan and returns ['coupon', 'original', 'discount', 'final'].
     *
     * @throws ValidationException
     */
    public function check(string $code, PortalUser $owner, Plan $plan, string $interval = 'monthly'): array
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['coupon_code' => $message]);

        $coupon = Coupon::where('code', strtoupper(trim($code)))->first();
        if (!$coupon || !$coupon->status) {
            $fail('This coupon code is not valid.');
        }
        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            $fail('This coupon is not active yet.');
        }
        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            $fail('This coupon has expired.');
        }
        if ((float) $plan->price <= 0) {
            $fail('Coupons apply to paid plans only.');
        }
        if (!$coupon->appliesToPlan($plan)) {
            $fail('This coupon cannot be used with the ' . $plan->getTranslation('name') . ' plan.');
        }
        if ($coupon->max_uses !== null && $coupon->redemptions()->count() >= $coupon->max_uses) {
            $fail('This coupon has reached its usage limit.');
        }
        if ($coupon->max_uses_per_user !== null
            && $coupon->redemptions()->where('portal_user_id', $owner->id)->count() >= $coupon->max_uses_per_user) {
            $fail("You've already used this coupon.");
        }

        $original = $plan->priceFor($interval);
        $discount = $coupon->discountFor($original);

        return [
            'coupon' => $coupon,
            'original' => $original,
            'discount' => $discount,
            'final' => round($original - $discount, 2),
        ];
    }

    /** Records the coupon on approval. Throws if it stopped being valid since the request was made. */
    public function redeem(PlanUpgradeRequest $request): ?CouponRedemption
    {
        if (!$request->coupon_code) {
            return null;
        }

        $result = $this->check($request->coupon_code, $request->portalUser, $request->plan, $request->billing_interval ?? 'monthly');
        $coupon = $result['coupon'];

        // A new plan replaces any earlier coupon still discounting the old one.
        CouponRedemption::where('portal_user_id', $request->portal_user_id)->usable()->update(['remaining_payments' => 0]);

        return CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'portal_user_id' => $request->portal_user_id,
            'plan_id' => $request->plan_id,
            'plan_upgrade_request_id' => $request->id,
            'discount_amount' => $result['discount'],
            'remaining_payments' => match ($coupon->duration) {
                'forever' => null,
                'repeating' => max(1, (int) $coupon->duration_months),
                default => 1,
            },
            'redeemed_at' => now(),
        ]);
    }

    /**
     * Discount to take off a payment being recorded for $owner on $plan (and consume one use),
     * as ['original', 'discount', 'amount', 'code'].
     */
    public function applyToPayment(PortalUser $owner, Plan $plan): array
    {
        $original = $plan->priceFor($owner->billing_interval ?? 'monthly');
        $redemption = CouponRedemption::with('coupon')
            ->where('portal_user_id', $owner->id)
            ->where('plan_id', $plan->id)
            ->usable()
            ->latest('redeemed_at')
            ->lockForUpdate()
            ->first();

        if (!$redemption) {
            return ['original' => $original, 'discount' => 0.0, 'amount' => $original, 'code' => null];
        }

        // Recomputed from the coupon when possible so a later plan price change stays consistent.
        $discount = $redemption->coupon ? $redemption->coupon->discountFor($original) : min($original, (float) $redemption->discount_amount);

        $redemption->used_payments++;
        if ($redemption->remaining_payments !== null) {
            $redemption->remaining_payments = max(0, $redemption->remaining_payments - 1);
        }
        $redemption->save();

        return [
            'original' => $original,
            'discount' => $discount,
            'amount' => round($original - $discount, 2),
            'code' => $redemption->coupon?->code,
        ];
    }
}
