<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Plan;
use App\Models\PlanPayment;
use App\Models\PlanUpgradeRequest;
use App\Models\PortalUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

/**
 * Plan subscriptions through Stripe Billing (monthly or yearly):
 *
 *  - Subscribe:  Stripe Checkout (mode=subscription) → plan activated at once, from the success
 *                redirect and/or the checkout.session.completed webhook (both idempotent).
 *  - Upgrade:    (higher plan, or monthly → yearly) charged immediately: the full new price minus
 *                the unused part of the current period, and a fresh billing period starts today.
 *                The plan only changes locally if that charge succeeds.
 *  - Downgrade:  (lower plan, or yearly → monthly) no refund/credit — the account keeps what it
 *                paid for until the period ends; the new plan starts at the next renewal.
 *  - Cancel:     at period end, then the account drops to the Free plan.
 *  - Renewals:   every paid Stripe invoice becomes a PlanPayment (invoice.paid webhook).
 *
 * Stripe Products/Prices/Coupons are created lazily and re-created whenever the local definition
 * changes, since those Stripe objects are immutable.
 */
class StripeBillingService
{
    private ?StripeClient $client = null;

    public static function enabled(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    /** Whether $plan can be bought online for $interval (free / one-time plans keep the manual flow). */
    public static function supportsPlan(Plan $plan, string $interval = 'monthly'): bool
    {
        if (!self::enabled() || !in_array($plan->billing_cycle, ['monthly', 'yearly'], true)) {
            return false;
        }

        return $interval === 'yearly' ? $plan->hasYearly() : (float) $plan->price > 0;
    }

    public function client(): StripeClient
    {
        return $this->client ??= new StripeClient(config('services.stripe.secret'));
    }

    private function currency(): string
    {
        return strtolower(config('services.stripe.currency', 'aed'));
    }

    // ------------------------------------------------------------------ catalog sync

    public function ensureCustomer(PortalUser $user): string
    {
        if ($user->stripe_customer_id) {
            return $user->stripe_customer_id;
        }

        $customer = $this->client()->customers->create([
            'email' => $user->email,
            'name' => $user->displayName(),
            'phone' => $user->phone ?: null,
            'metadata' => ['portal_user_id' => $user->id, 'type' => $user->type],
        ]);
        $user->forceFill(['stripe_customer_id' => $customer->id])->save();

        return $customer->id;
    }

    public function ensurePrice(Plan $plan, string $interval = 'monthly'): string
    {
        $yearly = $interval === 'yearly' && $plan->hasYearly();
        [$idCol, $amountCol] = $yearly ? ['stripe_yearly_price_id', 'stripe_yearly_price_amount'] : ['stripe_price_id', 'stripe_price_amount'];
        $amount = (int) round($plan->priceFor($yearly ? 'yearly' : 'monthly') * 100);

        if ($plan->{$idCol} && (int) $plan->{$amountCol} === $amount) {
            return $plan->{$idCol};
        }

        if (!$plan->stripe_product_id) {
            $product = $this->client()->products->create([
                'name' => 'MW Realty ' . $plan->getTranslation('name', 'en') . ' Plan',
                'metadata' => ['plan_id' => $plan->id],
            ]);
            $plan->stripe_product_id = $product->id;
        }

        $price = $this->client()->prices->create([
            'product' => $plan->stripe_product_id,
            'currency' => $this->currency(),
            'unit_amount' => $amount,
            'recurring' => ['interval' => ($yearly || $plan->billing_cycle === 'yearly') ? 'year' : 'month'],
            'metadata' => ['plan_id' => $plan->id, 'interval' => $yearly ? 'yearly' : 'monthly'],
        ]);

        $plan->forceFill([
            'stripe_product_id' => $plan->stripe_product_id,
            $idCol => $price->id,
            $amountCol => $amount,
        ])->save();

        return $price->id;
    }

    public function ensureCoupon(Coupon $coupon): string
    {
        $signature = sha1(implode('|', [
            $coupon->discount_type, $coupon->discount_value, $coupon->duration, $coupon->duration_months, $this->currency(),
        ]));

        if ($coupon->stripe_coupon_id && $coupon->stripe_coupon_signature === $signature) {
            return $coupon->stripe_coupon_id;
        }

        $params = [
            'name' => substr($coupon->code, 0, 40),
            'duration' => $coupon->duration,
            'metadata' => ['coupon_id' => $coupon->id, 'code' => $coupon->code],
        ];
        if ($coupon->duration === 'repeating') {
            $params['duration_in_months'] = max(1, (int) $coupon->duration_months);
        }
        if ($coupon->discount_type === Coupon::TYPE_PERCENT) {
            $params['percent_off'] = min(100, (float) $coupon->discount_value);
        } else {
            $params['amount_off'] = (int) round((float) $coupon->discount_value * 100);
            $params['currency'] = $this->currency();
        }

        $stripeCoupon = $this->client()->coupons->create($params);
        $coupon->forceFill(['stripe_coupon_id' => $stripeCoupon->id, 'stripe_coupon_signature' => $signature])->save();

        return $stripeCoupon->id;
    }

    /** [Plan, 'monthly'|'yearly'] for a Stripe price id, or [null, null]. */
    public function planForPrice(?string $priceId): array
    {
        if (!$priceId) {
            return [null, null];
        }
        if ($plan = Plan::where('stripe_price_id', $priceId)->first()) {
            return [$plan, $plan->billing_cycle === 'yearly' ? 'yearly' : 'monthly'];
        }
        if ($plan = Plan::where('stripe_yearly_price_id', $priceId)->first()) {
            return [$plan, 'yearly'];
        }

        return [null, null];
    }

    // ------------------------------------------------------------------ change rules

    /**
     * 'same' | 'upgrade' | 'downgrade' | 'cancel' for moving $user's subscription to $plan/$interval.
     * Higher tier (by monthly price) or same plan monthly → yearly is an upgrade; the reverse is a
     * downgrade; a free plan means ending the subscription.
     */
    public function classifyChange(PortalUser $user, Plan $plan, string $interval): string
    {
        $current = $user->plan;
        $currentInterval = $user->billing_interval ?: 'monthly';

        if ((float) $plan->price <= 0) {
            return 'cancel';
        }
        if (!$current || (float) $current->price <= 0) {
            return 'upgrade';
        }
        if ($current->id === $plan->id) {
            return $currentInterval === $interval ? 'same' : ($interval === 'yearly' ? 'upgrade' : 'downgrade');
        }

        return (float) $plan->price > (float) $current->price ? 'upgrade' : 'downgrade';
    }

    /**
     * What a switch will cost, for the confirmation modal. For upgrades the numbers come from
     * Stripe's own invoice preview (same proration_date is then used to perform the change, so
     * the charge matches what was shown).
     */
    public function previewChange(PortalUser $user, Plan $plan, string $interval, ?array $pricing): array
    {
        $type = $this->classifyChange($user, $plan, $interval);
        $newPrice = $plan->priceFor($interval);
        $renewsAt = $user->subscription_renews_at;
        $recurringDiscount = $pricing && $pricing['coupon']->duration !== 'once' ? $pricing['discount'] : 0.0;

        $base = [
            'type' => $type,
            'plan' => $plan->getTranslation('name'),
            'interval' => $interval,
            'current_plan' => $user->plan?->getTranslation('name'),
            'current_interval' => $user->billing_interval ?: 'monthly',
            'current_price' => $user->plan?->priceFor($user->billing_interval ?: 'monthly') ?? 0.0,
            'new_price' => $newPrice,
            'renews_at' => $renewsAt?->format('d M Y'),
            'coupon' => $pricing['coupon']->code ?? null,
        ];

        if ($type === 'cancel' || $type === 'downgrade') {
            return $base + [
                'due_now' => 0.0,
                'lines' => [],
                'effective_date' => $renewsAt?->format('d M Y'),
                'next_amount' => round($newPrice - ($pricing['discount'] ?? 0), 2),
                'next_date' => $renewsAt?->format('d M Y'),
            ];
        }
        if ($type === 'same') {
            return $base + ['due_now' => 0.0, 'lines' => []];
        }

        $prorationDate = now()->timestamp;
        $subscription = $this->client()->subscriptions->retrieve($user->stripe_subscription_id);
        $params = [
            'customer' => $user->stripe_customer_id,
            'subscription' => $subscription->id,
            'subscription_details' => [
                'items' => [['id' => $subscription->items->data[0]->id, 'price' => $this->ensurePrice($plan, $interval)]],
                'proration_behavior' => 'always_invoice',
                'billing_cycle_anchor' => 'now',
            ],
        ];
        if ($pricing) {
            $params['discounts'] = [['coupon' => $this->ensureCoupon($pricing['coupon'])]];
        }

        $preview = $this->client()->invoices->createPreview($params)->toArray();

        $lines = collect($preview['lines']['data'] ?? [])->map(fn ($line) => [
            'description' => $this->lineLabel($line),
            'amount' => round(($line['amount'] ?? 0) / 100, 2),
        ])->values()->all();
        $discount = collect($preview['total_discount_amounts'] ?? [])->sum('amount') / 100;
        $credit = max(0, -(int) ($preview['starting_balance'] ?? 0)) / 100;
        $total = ($preview['total'] ?? 0) / 100;

        return $base + [
            'lines' => $lines,
            'subtotal' => round(($preview['subtotal'] ?? 0) / 100, 2),
            'discount' => round($discount, 2),
            'credit' => round(min($credit, max(0, $total)), 2),
            'due_now' => round(($preview['amount_due'] ?? 0) / 100, 2),
            'next_date' => now()->add($interval === 'yearly' ? '1 year' : '1 month')->format('d M Y'),
            'next_amount' => round($newPrice - $recurringDiscount, 2),
            'proration_date' => $prorationDate,
        ];
    }

    // ------------------------------------------------------------------ portal actions

    /** Starts Stripe Checkout for a first subscription and returns the URL to redirect to. */
    public function createCheckout(PortalUser $user, Plan $plan, string $interval, ?array $pricing): string
    {
        $request = PlanUpgradeRequest::create([
            'portal_user_id' => $user->id,
            'plan_id' => $plan->id,
            'billing_interval' => $interval,
            'current_plan_id' => $user->plan_id,
            'status' => 'checkout',
            'requested_at' => now(),
            'coupon_id' => $pricing['coupon']->id ?? null,
            'coupon_code' => $pricing['coupon']->code ?? null,
            'original_price' => $plan->priceFor($interval),
            'discount_amount' => $pricing['discount'] ?? null,
            'final_price' => $pricing['final'] ?? $plan->priceFor($interval),
        ]);

        $metadata = [
            'portal_user_id' => $user->id,
            'plan_id' => $plan->id,
            'interval' => $interval,
            'upgrade_request_id' => $request->id,
            'coupon_id' => $pricing['coupon']->id ?? '',
        ];

        $params = [
            'mode' => 'subscription',
            'customer' => $this->ensureCustomer($user),
            'client_reference_id' => (string) $request->id,
            'line_items' => [['price' => $this->ensurePrice($plan, $interval), 'quantity' => 1]],
            'metadata' => $metadata,
            'subscription_data' => ['metadata' => $metadata],
            'success_url' => route('portal.plans.checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('portal.plans.index') . '?checkout=cancelled',
        ];
        if ($pricing) {
            $params['discounts'] = [['coupon' => $this->ensureCoupon($pricing['coupon'])]];
        }

        $session = $this->client()->checkout->sessions->create($params);
        $request->update(['stripe_checkout_session_id' => $session->id]);

        return $session->url;
    }

    /**
     * Applies a plan/interval change for a subscribed account and returns a success message.
     * Upgrades throw (Stripe\Exception\ApiErrorException) without changing anything if the charge fails.
     */
    public function changePlan(PortalUser $user, Plan $plan, string $interval, ?array $pricing, ?int $prorationDate = null): string
    {
        return match ($this->classifyChange($user, $plan, $interval)) {
            'upgrade' => $this->upgradeNow($user, $plan, $interval, $pricing, $prorationDate),
            'downgrade' => $this->scheduleDowngrade($user, $plan, $interval, $pricing),
            'cancel' => $this->cancelAtPeriodEnd($user, $plan),
            default => 'You are already on this plan.',
        };
    }

    private function upgradeNow(PortalUser $user, Plan $plan, string $interval, ?array $pricing, ?int $prorationDate): string
    {
        $subscription = $this->client()->subscriptions->retrieve($user->stripe_subscription_id);

        $params = [
            'items' => [['id' => $subscription->items->data[0]->id, 'price' => $this->ensurePrice($plan, $interval)]],
            'proration_behavior' => 'always_invoice',
            'billing_cycle_anchor' => 'now',
            'payment_behavior' => 'error_if_incomplete',
            'cancel_at_period_end' => false,
            'metadata' => ['portal_user_id' => $user->id, 'plan_id' => $plan->id, 'interval' => $interval, 'coupon_id' => $pricing['coupon']->id ?? ''],
        ];
        // No proration_date here: Stripe rejects it together with billing_cycle_anchor=now, so the
        // credit is computed at the moment of confirming — seconds after the preview the user saw.
        if ($pricing) {
            $params['discounts'] = [['coupon' => $this->ensureCoupon($pricing['coupon'])]];
        }

        $updated = $this->client()->subscriptions->update($subscription->id, $params);

        DB::transaction(function () use ($user, $plan, $interval, $pricing, $updated) {
            $this->logChange($user, $plan, $interval, $pricing, 'Upgraded online via Stripe');
            $user->forceFill([
                'plan_id' => $plan->id,
                'billing_interval' => $interval,
                'scheduled_plan_id' => null,
                'scheduled_interval' => null,
            ])->save();
            $this->applySubscription($user->fresh(), $updated);
        });

        return 'Your plan is now ' . $plan->getTranslation('name') . ' (' . $interval . '). The amount shown was charged to your card.';
    }

    /**
     * Lower plan / shorter interval: nothing refunded — the subscription's price is swapped
     * without proration, so Stripe bills the new price from the next renewal, while locally the
     * account keeps its current plan until then.
     */
    private function scheduleDowngrade(PortalUser $user, Plan $plan, string $interval, ?array $pricing): string
    {
        $subscription = $this->client()->subscriptions->retrieve($user->stripe_subscription_id);

        $params = [
            'items' => [['id' => $subscription->items->data[0]->id, 'price' => $this->ensurePrice($plan, $interval)]],
            'proration_behavior' => 'none',
            'cancel_at_period_end' => false,
            'metadata' => ['portal_user_id' => $user->id, 'scheduled_plan_id' => $plan->id, 'interval' => $interval],
        ];
        if ($pricing) {
            $params['discounts'] = [['coupon' => $this->ensureCoupon($pricing['coupon'])]];
        }

        $updated = $this->client()->subscriptions->update($subscription->id, $params);

        DB::transaction(function () use ($user, $plan, $interval, $pricing, $updated) {
            $this->logChange($user, $plan, $interval, $pricing, 'Downgrade scheduled for next renewal');
            $user->forceFill(['scheduled_plan_id' => $plan->id, 'scheduled_interval' => $interval])->save();
            $this->applySubscription($user->fresh(), $updated);
        });

        return 'Done — you keep ' . $user->plan?->getTranslation('name') . ' until ' . $user->fresh()->subscription_renews_at?->format('d M Y')
            . ', then ' . $plan->getTranslation('name') . ' (' . $interval . ') starts. Nothing is charged today.';
    }

    /** Undo a scheduled downgrade: the subscription goes back to the current plan's price. */
    public function keepCurrentPlan(PortalUser $user): void
    {
        if (!$user->scheduled_plan_id || !$user->plan) {
            return;
        }

        $subscription = $this->client()->subscriptions->retrieve($user->stripe_subscription_id);
        $updated = $this->client()->subscriptions->update($subscription->id, [
            'items' => [['id' => $subscription->items->data[0]->id, 'price' => $this->ensurePrice($user->plan, $user->billing_interval ?: 'monthly')]],
            'proration_behavior' => 'none',
        ]);

        $user->forceFill(['scheduled_plan_id' => null, 'scheduled_interval' => null])->save();
        $this->applySubscription($user->fresh(), $updated);
    }

    /** "Switch to Free" / cancel: keeps the paid plan until the period already paid for ends. */
    public function cancelAtPeriodEnd(PortalUser $user, ?Plan $freePlan = null): string
    {
        $subscription = $this->client()->subscriptions->update($user->stripe_subscription_id, ['cancel_at_period_end' => true]);
        $user->forceFill(['scheduled_plan_id' => null, 'scheduled_interval' => null])->save();
        $this->applySubscription($user->fresh(), $subscription);

        return 'Your subscription will end on ' . $user->fresh()->subscription_renews_at?->format('d M Y')
            . ' and your account moves to the ' . ($freePlan?->getTranslation('name') ?? 'Free') . ' plan then.';
    }

    public function resume(PortalUser $user): void
    {
        $subscription = $this->client()->subscriptions->update($user->stripe_subscription_id, ['cancel_at_period_end' => false]);
        $this->applySubscription($user, $subscription);
    }

    /** Stripe-hosted page for updating the card and downloading invoices. */
    public function billingPortalUrl(PortalUser $user): string
    {
        return $this->client()->billingPortal->sessions->create([
            'customer' => $this->ensureCustomer($user),
            'return_url' => route('portal.plans.index'),
        ])->url;
    }

    /** Fallback for missed webhooks: a scheduled downgrade whose date has passed takes effect. */
    public function applyDueScheduledChange(PortalUser $user): void
    {
        if ($user->scheduled_plan_id && $user->subscription_renews_at && $user->subscription_renews_at->isPast()) {
            $user->forceFill([
                'plan_id' => $user->scheduled_plan_id,
                'billing_interval' => $user->scheduled_interval ?: 'monthly',
                'scheduled_plan_id' => null,
                'scheduled_interval' => null,
            ])->save();
        }
    }

    // ------------------------------------------------------------------ fulfilment + webhooks

    /** Activates the plan for a completed Checkout Session. Safe to call repeatedly. */
    public function fulfillCheckout(string $sessionId): ?PlanUpgradeRequest
    {
        $session = $this->client()->checkout->sessions->retrieve($sessionId, ['expand' => ['subscription', 'invoice']]);

        if ($session->status !== 'complete' || !in_array($session->payment_status, ['paid', 'no_payment_required'], true)) {
            return null;
        }

        return DB::transaction(function () use ($session) {
            $request = PlanUpgradeRequest::where('stripe_checkout_session_id', $session->id)->lockForUpdate()->first();
            if (!$request) {
                return null;
            }
            if ($request->status === 'approved') {
                return $request;
            }

            $user = $request->portalUser;
            $user->forceFill([
                'plan_id' => $request->plan_id,
                'billing_interval' => $request->billing_interval ?: 'monthly',
                'scheduled_plan_id' => null,
                'scheduled_interval' => null,
            ])->save();
            $request->update(['status' => 'approved', 'decided_at' => now(), 'decision_note' => 'Paid online via Stripe']);
            $this->recordCouponUse($request);

            if ($session->subscription) {
                $this->applySubscription($user->fresh(), $session->subscription);
            }
            if ($session->invoice) {
                $this->recordInvoice($session->invoice);
            }

            return $request;
        });
    }

    /** Syncs local subscription fields (and plan, if changed in Stripe) from a Subscription object. */
    public function applySubscription(?PortalUser $user, $subscription): void
    {
        $sub = $this->toArray($subscription);
        $user ??= $this->userForSubscription($sub);
        if (!$user) {
            return;
        }

        if (($sub['status'] ?? null) === 'canceled') {
            $this->endSubscription($user, $sub['id']);
            return;
        }

        $periodEnd = $sub['current_period_end'] ?? ($sub['items']['data'][0]['current_period_end'] ?? null);
        $newRenewsAt = $periodEnd ? Carbon::createFromTimestamp($periodEnd) : null;
        $data = [
            'stripe_customer_id' => is_array($sub['customer'] ?? null) ? $sub['customer']['id'] : ($sub['customer'] ?? $user->stripe_customer_id),
            'stripe_subscription_id' => $sub['id'],
            'subscription_status' => $sub['status'] ?? null,
            'subscription_renews_at' => $newRenewsAt,
            'subscription_cancel_at_period_end' => (bool) ($sub['cancel_at_period_end'] ?? false),
        ];

        [$plan, $interval] = $this->planForPrice($sub['items']['data'][0]['price']['id'] ?? null);
        if ($plan && in_array($sub['status'] ?? null, ['active', 'trialing'], true)) {
            $isScheduled = $user->scheduled_plan_id === $plan->id && ($user->scheduled_interval ?: 'monthly') === $interval;
            $periodRolledOver = $user->subscription_renews_at && $newRenewsAt && $newRenewsAt->gt($user->subscription_renews_at->copy()->addMinute());

            if ($isScheduled && !$periodRolledOver) {
                // Downgrade already priced in Stripe, but the paid-for period isn't over yet.
            } elseif ($plan->id !== $user->plan_id || $interval !== ($user->billing_interval ?: 'monthly')) {
                $data['plan_id'] = $plan->id;
                $data['billing_interval'] = $interval;
                $data['scheduled_plan_id'] = null;
                $data['scheduled_interval'] = null;
            }
        }

        $user->forceFill($data)->save();
    }

    /** customer.subscription.deleted — the paid period is over: drop to the Free plan. */
    public function endSubscription(PortalUser $user, string $subscriptionId): void
    {
        if ($user->stripe_subscription_id && $user->stripe_subscription_id !== $subscriptionId) {
            return; // an older subscription ending after the user already started a new one
        }

        $user->forceFill([
            'plan_id' => Plan::defaultFree()?->id,
            'billing_interval' => null,
            'stripe_subscription_id' => null,
            'subscription_status' => 'canceled',
            'subscription_renews_at' => null,
            'subscription_cancel_at_period_end' => false,
            'scheduled_plan_id' => null,
            'scheduled_interval' => null,
            'payment_status' => 'unpaid',
        ])->save();
    }

    /** invoice.paid — records the payment (first charge, upgrades and every renewal). */
    public function recordInvoice($invoice): ?PlanPayment
    {
        $inv = $this->toArray($invoice);
        if (($inv['status'] ?? null) !== 'paid' || empty($inv['id'])) {
            return null;
        }

        $user = PortalUser::where('stripe_customer_id', is_array($inv['customer'] ?? null) ? $inv['customer']['id'] : ($inv['customer'] ?? ''))->first();
        if (!$user) {
            return null;
        }

        // Nothing actually charged on a plan change (e.g. fully covered by account credit) isn't a payment.
        if ((int) ($inv['amount_paid'] ?? 0) === 0 && ($inv['billing_reason'] ?? null) === 'subscription_update') {
            return null;
        }

        // Proration invoices list "Unused time on <old plan>" (negative) before the new plan's
        // charge (positive); the plan being paid for is the one on a positive line.
        $priceId = null;
        $fallbackPriceId = null;
        foreach ($inv['lines']['data'] ?? [] as $line) {
            $linePrice = $line['pricing']['price_details']['price'] ?? ($line['price']['id'] ?? null);
            if (!$linePrice) {
                continue;
            }
            $fallbackPriceId ??= $linePrice;
            if (($line['amount'] ?? 0) > 0) {
                $priceId = $linePrice;
            }
        }
        [$plan, $interval] = $this->planForPrice($priceId ?? $fallbackPriceId);
        $plan ??= $user->plan;
        $interval ??= $user->billing_interval ?: 'monthly';

        $paid = ($inv['amount_paid'] ?? 0) / 100;
        $discount = collect($inv['total_discount_amounts'] ?? [])->sum('amount') / 100;
        $paidAt = Carbon::createFromTimestamp($inv['status_transitions']['paid_at'] ?? $inv['created'] ?? time());
        $couponCode = $discount > 0
            ? CouponRedemption::with('coupon')->where('portal_user_id', $user->id)->latest('redeemed_at')->first()?->coupon?->code
            : null;

        $attributes = [
            'idempotency_key' => 'stripe:' . $inv['id'],
            'portal_user_id' => $user->id,
            'plan_id' => $plan?->id,
            'plan_name' => $plan?->getTranslation('name') ?? 'Subscription',
            'amount' => $paid,
            'status' => 'paid',
            'failure_reason' => null,
            'attempts' => max(1, (int) ($inv['attempt_count'] ?? 1)),
            'original_amount' => $discount > 0 ? $paid + $discount : null,
            'discount_amount' => $discount > 0 ? $discount : null,
            'coupon_code' => $couponCode,
            'billing_cycle' => $interval,
            'period_year' => $paidAt->year,
            'period_month' => $interval === 'monthly' ? $paidAt->month : null,
            'paid_at' => $paidAt->toDateString(),
            'stripe_hosted_invoice_url' => $inv['hosted_invoice_url'] ?? null,
        ];

        // A renewal that failed first is the same invoice — the retry that succeeds turns that
        // Failed row into Paid instead of adding a second one.
        $payment = PlanPayment::where('stripe_invoice_id', $inv['id'])->first();
        if ($payment && !$payment->isPaid()) {
            $payment->update($attributes);
        } elseif (!$payment) {
            $payment = PlanPayment::create(['stripe_invoice_id' => $inv['id']] + $attributes);
        }

        $user->forceFill(['payment_status' => 'paid', 'last_payment_at' => $paidAt->toDateString()])->save();

        // Invoice email (PDF attached) once the payment row is committed — sendOnce() is idempotent.
        DB::afterCommit(fn () => app(InvoiceService::class)->sendOnce($payment));

        return $payment;
    }

    /**
     * invoice.payment_failed — recorded as a Failed payment (Stripe keeps retrying the same
     * invoice; see recordInvoice for the flip to Paid) and the account is flagged unpaid.
     */
    public function markPaymentFailed($invoice): ?PlanPayment
    {
        $inv = $this->toArray($invoice);
        $customer = is_array($inv['customer'] ?? null) ? $inv['customer']['id'] : ($inv['customer'] ?? null);
        $user = $customer ? PortalUser::where('stripe_customer_id', $customer)->first() : null;
        if (!$user || empty($inv['id'])) {
            return null;
        }
        $user->forceFill(['payment_status' => 'unpaid'])->save();

        $existing = PlanPayment::where('stripe_invoice_id', $inv['id'])->first();
        if ($existing?->isPaid()) {
            return $existing; // events can arrive out of order — never downgrade a paid row
        }

        [$plan, $interval] = $this->planForPrice($this->paidLinePrice($inv));
        $plan ??= $user->plan;
        $interval ??= $user->billing_interval ?: 'monthly';
        $attemptAt = Carbon::createFromTimestamp($inv['created'] ?? time());
        $nextRetry = !empty($inv['next_payment_attempt']) ? Carbon::createFromTimestamp($inv['next_payment_attempt'])->format('d M Y') : null;

        $attributes = [
            'idempotency_key' => 'stripe:' . $inv['id'],
            'portal_user_id' => $user->id,
            'plan_id' => $plan?->id,
            'plan_name' => $plan?->getTranslation('name') ?? 'Subscription',
            'amount' => ($inv['amount_due'] ?? 0) / 100,
            'status' => 'failed',
            'failure_reason' => trim(($inv['last_finalization_error']['message'] ?? 'Card payment was declined.')
                . ($nextRetry ? " Stripe will retry on {$nextRetry}." : '')),
            'attempts' => max(1, (int) ($inv['attempt_count'] ?? 1)),
            'billing_cycle' => $interval,
            'period_year' => $attemptAt->year,
            'period_month' => $interval === 'monthly' ? $attemptAt->month : null,
            'paid_at' => $attemptAt->toDateString(),
            'stripe_hosted_invoice_url' => $inv['hosted_invoice_url'] ?? null,
        ];

        return $existing
            ? tap($existing)->update($attributes)
            : PlanPayment::create(['stripe_invoice_id' => $inv['id']] + $attributes);
    }

    /** Price id of the line actually being charged (positive amount) on an invoice. */
    private function paidLinePrice(array $inv): ?string
    {
        $fallback = null;
        foreach ($inv['lines']['data'] ?? [] as $line) {
            $price = $line['pricing']['price_details']['price'] ?? ($line['price']['id'] ?? null);
            if (!$price) {
                continue;
            }
            $fallback ??= $price;
            if (($line['amount'] ?? 0) > 0) {
                return $price;
            }
        }

        return $fallback;
    }

    // ------------------------------------------------------------------ helpers

    private function logChange(PortalUser $user, Plan $plan, string $interval, ?array $pricing, string $note): void
    {
        $request = PlanUpgradeRequest::create([
            'portal_user_id' => $user->id,
            'plan_id' => $plan->id,
            'billing_interval' => $interval,
            'current_plan_id' => $user->plan_id,
            'status' => 'approved',
            'requested_at' => now(),
            'decided_at' => now(),
            'decision_note' => $note,
            'coupon_id' => $pricing['coupon']->id ?? null,
            'coupon_code' => $pricing['coupon']->code ?? null,
            'original_price' => $plan->priceFor($interval),
            'discount_amount' => $pricing['discount'] ?? null,
            'final_price' => $pricing['final'] ?? $plan->priceFor($interval),
        ]);
        $this->recordCouponUse($request);
    }

    /** Counts the coupon toward its limits. Stripe applies the actual discount, so no local uses remain. */
    private function recordCouponUse(PlanUpgradeRequest $request): void
    {
        if (!$request->coupon_id || CouponRedemption::where('plan_upgrade_request_id', $request->id)->exists()) {
            return;
        }

        CouponRedemption::where('portal_user_id', $request->portal_user_id)->usable()->update(['remaining_payments' => 0]);
        CouponRedemption::create([
            'coupon_id' => $request->coupon_id,
            'portal_user_id' => $request->portal_user_id,
            'plan_id' => $request->plan_id,
            'plan_upgrade_request_id' => $request->id,
            'discount_amount' => (float) $request->discount_amount,
            'remaining_payments' => 0,
            'redeemed_at' => now(),
        ]);
    }

    /** Human label for a preview invoice line ("Unused time on Basic…" / "Pro plan – 1 month"). */
    private function lineLabel(array $line): string
    {
        $description = (string) ($line['description'] ?? '');
        [$plan, $interval] = $this->planForPrice($line['pricing']['price_details']['price'] ?? ($line['price']['id'] ?? null));
        $name = $plan?->getTranslation('name') ?? 'Plan';

        if (($line['amount'] ?? 0) < 0 || str_starts_with(strtolower($description), 'unused')) {
            return 'Credit for unused time on ' . $name;
        }
        if (str_starts_with(strtolower($description), 'remaining')) {
            return 'Remaining time on ' . $name;
        }

        return $name . ' plan — 1 ' . ($interval === 'yearly' ? 'year' : 'month');
    }

    private function userForSubscription(array $sub): ?PortalUser
    {
        return PortalUser::where('stripe_subscription_id', $sub['id'] ?? '')->first()
            ?? (isset($sub['metadata']['portal_user_id']) ? PortalUser::find($sub['metadata']['portal_user_id']) : null)
            ?? PortalUser::where('stripe_customer_id', is_array($sub['customer'] ?? null) ? $sub['customer']['id'] : ($sub['customer'] ?? ''))->first();
    }

    private function toArray($object): array
    {
        if (is_array($object)) {
            return $object;
        }
        if (is_string($object)) {
            return ['id' => $object];
        }

        return $object ? $object->toArray() : [];
    }

    public static function logError(\Throwable $e, string $context): void
    {
        Log::error("Stripe {$context} failed: " . $e->getMessage());
    }
}
