<?php

namespace App\Http\Controllers\Crm\Account;

use App\Models\CmsKit\Admin;
use App\Models\Plan;
use App\Models\PlanPayment;
use App\Models\PlanUpgradeRequest;
use App\Models\PortalUser;
use App\Notifications\PlanUpgradeRequestedNotification;
use App\Services\CouponService;
use App\Services\InvoiceService;
use App\Services\StripeBillingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * @group CRM Plans
 *
 * Plans & billing for the signed-in agent / company. With Stripe on: a first subscription is paid
 * on our checkout page (card fields are Stripe Elements — POST /plans/checkout/intent, confirm the
 * card with Stripe, then POST /plans/checkout/complete); an existing one is upgraded now, downgraded
 * at renewal or cancelled at period end (preview first with POST /plans/change-preview). Without
 * Stripe, a plan change is a request Super Admin approves. An agency agent is on the agency's plan
 * (`agency_plan`) and has nothing to buy.
 */
class PlanController extends Controller
{
    private const TIER_ICONS = ['fa-seedling', 'fa-paper-plane', 'fa-rocket', 'fa-crown', 'fa-gem'];

    /**
     * The Plans page
     *
     * Current plan, usage and subscription; every active plan with its price, features and — per
     * billing interval — what its button does (`action.kind`: current, scheduled, change, checkout,
     * pending, blocked, request).
     */
    public function index()
    {
        $owner = $this->owner();

        // An agency agent is covered by the agency's plan: show that instead of plans to buy.
        if ($owner->isOnAgencyPlan()) {
            $agency = $owner->company->loadMissing('plan');
            $plan = $agency->plan;
            $limit = $plan && !$plan->isUnlimited() ? (int) $plan->property_limit : null;

            return response()->json([
                'agency_plan' => [
                    'agency' => $agency->displayName(),
                    'plan' => $plan?->getTranslation('name'),
                    'used' => $agency->properties()->count(),
                    'limit' => $limit,
                    'entitlements' => $plan ? collect($plan->entitlementLines())->map(fn ($l) => ['text' => $l[0], 'included' => (bool) $l[1]])->values() : [],
                    'own_subscription_ends' => $owner->hasStripeSubscription() && $owner->subscription_cancel_at_period_end
                        ? $owner->subscription_renews_at?->toIso8601String() : null,
                ],
                'has_payments' => $owner->planPayments()->exists(),
            ]);
        }

        $stripeEnabled = StripeBillingService::enabled();
        $billing = $stripeEnabled ? app(StripeBillingService::class) : null;
        if ($billing) {
            $billing->applyDueScheduledChange($owner);
            $owner->refresh();
        }

        $plans = Plan::active()->orderBy('order_index')->get();
        $pendingRequest = $owner->planUpgradeRequests()->pending()->latest()->first();
        $currentPlan = $owner->plan;
        $subscribed = $stripeEnabled && $owner->hasStripeSubscription();
        $currentInterval = $owner->billing_interval ?: 'monthly';
        $scheduled = $subscribed ? $owner->scheduledPlan : null;
        $used = $owner->properties()->count();
        $limit = $currentPlan && !$currentPlan->isUnlimited() ? (int) $currentPlan->property_limit : null;

        return response()->json([
            'agency_plan' => null,
            'stripe_enabled' => $stripeEnabled,
            'subscribed' => $subscribed,
            'has_payments' => $owner->planPayments()->exists(),
            'has_yearly' => $plans->contains(fn ($p) => $p->hasYearly()),
            'max_saving' => (int) $plans->max(fn ($p) => $p->yearlySavingsPercent()),
            'default_interval' => $subscribed ? $currentInterval : 'monthly',
            'current' => [
                'plan' => $currentPlan?->getTranslation('name'),
                'paid' => $currentPlan && (float) $currentPlan->price > 0,
                'interval' => $currentInterval,
                'price' => $currentPlan?->priceFor($currentInterval) ?? 0,
                'status' => $subscribed ? ([
                    'active' => ['pl-sub__badge--ok', 'Active'], 'trialing' => ['pl-sub__badge--ok', 'Trial'],
                    'past_due' => ['pl-sub__badge--warn', 'Payment failed — retrying'], 'unpaid' => ['pl-sub__badge--warn', 'Unpaid'],
                ][$owner->subscription_status] ?? ['pl-sub__badge--warn', ucfirst((string) $owner->subscription_status)]) : null,
                'past_due' => $owner->subscription_status === 'past_due',
                'pending_request' => $pendingRequest?->plan?->getTranslation('name'),
                'used' => $used,
                'limit' => $limit,
                'cancel_at_period_end' => (bool) $owner->subscription_cancel_at_period_end,
                'renews_at' => $owner->subscription_renews_at?->toIso8601String(),
                'scheduled' => $scheduled ? [
                    'plan' => $scheduled->getTranslation('name'),
                    'interval' => $owner->scheduled_interval ?: 'monthly',
                    'price' => $scheduled->priceFor($owner->scheduled_interval ?: 'monthly'),
                ] : null,
            ],
            'show_coupon' => !$pendingRequest,
            'plans' => $plans->values()->map(fn (Plan $plan, $index) => $this->planCard($plan, $index, $owner, $billing, $subscribed, $pendingRequest)),
            'note' => $stripeEnabled
                ? 'Payments are processed securely by Stripe — MW Realty never sees your card details.'
                : 'Plan changes are reviewed by MW Realty and applied to your account — no payment is taken on this page.',
        ]);
    }

    /**
     * Live coupon preview
     *
     * The discounted price of every paid plan and interval, or why the coupon doesn't apply to it.
     *
     * @bodyParam coupon_code string required Example: WELCOME10
     */
    public function checkCoupon(Request $request)
    {
        $request->validate(['coupon_code' => 'required|string|max:40']);
        $owner = $this->owner();
        $service = app(CouponService::class);

        $results = [];
        $anyValid = false;
        $firstError = null;
        foreach (Plan::active()->where('price', '>', 0)->get() as $plan) {
            foreach (Plan::INTERVALS as $interval) {
                if ($interval === 'yearly' && !$plan->hasYearly()) {
                    continue;
                }
                try {
                    $r = $service->check($request->input('coupon_code'), $owner, $plan, $interval);
                    $anyValid = true;
                    $results[$plan->id][$interval] = [
                        'valid' => true,
                        'original' => $r['original'],
                        'discount' => $r['discount'],
                        'final' => $r['final'],
                        'label' => $r['coupon']->discountLabel() . ' ' . $r['coupon']->durationLabel(),
                    ];
                } catch (ValidationException $e) {
                    $firstError ??= collect($e->errors())->flatten()->first();
                    $results[$plan->id][$interval] = ['valid' => false, 'message' => collect($e->errors())->flatten()->first()];
                }
            }
        }

        if (!$anyValid) {
            return response()->json(['success' => false, 'message' => $firstError ?? 'This coupon code is not valid.'], 422);
        }

        return response()->json(['success' => true, 'code' => strtoupper(trim($request->input('coupon_code'))), 'plans' => $results]);
    }

    /**
     * Choose a plan
     *
     * With Stripe: an existing subscription changes now (upgrade / downgrade / cancel); with none,
     * the answer is `checkout` — open the checkout page with those values. Without Stripe (or a
     * plan Stripe can't sell), a request for Super Admin is created.
     *
     * @bodyParam plan_id integer required Example: 3
     * @bodyParam interval string monthly or yearly. Example: monthly
     * @bodyParam coupon_code string Example: WELCOME10
     * @bodyParam proration_date integer From the change preview, so the charge matches it. Example: 1760000000
     */
    public function store(Request $request)
    {
        [$owner, $plan, $interval, $pricing] = $this->resolveChange($request);
        abort_if($owner->hasPendingPlanUpgradeRequest(), 422, 'You already have a pending plan request.');

        // Online payment: new subscription → our checkout; existing one → upgrade now /
        // downgrade at renewal / cancel at period end (see StripeBillingService::classifyChange()).
        if (StripeBillingService::enabled()) {
            $billing = app(StripeBillingService::class);
            try {
                if ($owner->hasStripeSubscription()) {
                    if ((float) $plan->price <= 0 || StripeBillingService::supportsPlan($plan, $interval)) {
                        return response()->json(['success' => true, 'message' => $billing->changePlan($owner, $plan, $interval, $pricing, $request->integer('proration_date') ?: null)]);
                    }
                } elseif (StripeBillingService::supportsPlan($plan, $interval)) {
                    return response()->json(['success' => true, 'checkout' => array_filter([
                        'plan_id' => $plan->id,
                        'interval' => $interval,
                        'coupon_code' => $pricing['coupon']->code ?? null,
                    ])]);
                }
            } catch (\Stripe\Exception\ApiErrorException $e) {
                StripeBillingService::logError($e, 'plan change');

                return response()->json(['message' => 'Payment could not be completed: ' . $e->getMessage()], 422);
            }
        }

        abort_if((int) $plan->id === (int) $owner->plan_id && $interval === ($owner->billing_interval ?: 'monthly'), 422, 'You are already on this plan.');

        $upgradeRequest = PlanUpgradeRequest::create([
            'portal_user_id' => $owner->id,
            'plan_id' => $plan->id,
            'billing_interval' => $interval,
            'current_plan_id' => $owner->plan_id,
            'status' => 'pending',
            'requested_at' => now(),
            'coupon_id' => $pricing['coupon']->id ?? null,
            'coupon_code' => $pricing['coupon']->code ?? null,
            'original_price' => $pricing['original'] ?? $plan->priceFor($interval),
            'discount_amount' => $pricing['discount'] ?? null,
            'final_price' => $pricing['final'] ?? $plan->priceFor($interval),
        ]);

        try {
            Admin::role('superadmin')->get()->each(
                fn (Admin $admin) => $admin->notify(new PlanUpgradeRequestedNotification($upgradeRequest))
            );
        } catch (\Throwable $e) {
            Log::error('Failed to create plan-upgrade-requested bell notification: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Upgrade request submitted' . ($pricing ? ' with coupon ' . $pricing['coupon']->code : '') . ' — Super Admin will review it shortly.',
        ]);
    }

    /**
     * Preview a plan change
     *
     * What switching an existing subscription will cost — Stripe's own numbers for an upgrade.
     *
     * @bodyParam plan_id integer required Example: 3
     * @bodyParam interval string Example: yearly
     * @bodyParam coupon_code string Example: WELCOME10
     */
    public function previewChange(Request $request, StripeBillingService $billing)
    {
        abort_unless(StripeBillingService::enabled(), 404);
        [$owner, $plan, $interval, $pricing] = $this->resolveChange($request);
        abort_unless($owner->hasStripeSubscription(), 422, 'No active subscription to change.');

        try {
            return response()->json(['success' => true] + $billing->previewChange($owner, $plan, $interval, $pricing));
        } catch (\Stripe\Exception\ApiErrorException $e) {
            StripeBillingService::logError($e, 'change preview');

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /** Keep the current plan (undo a scheduled downgrade) */
    public function keepCurrentPlan(StripeBillingService $billing)
    {
        $owner = $this->owner();
        abort_unless($owner->hasStripeSubscription(), 404);
        $billing->keepCurrentPlan($owner);

        return response()->json(['success' => true, 'message' => 'Scheduled change cancelled — you stay on ' . $owner->plan?->getTranslation('name') . '.']);
    }

    /** Turn off auto-renew (the plan lasts until the end of the paid period) */
    public function cancelSubscription(StripeBillingService $billing)
    {
        $owner = $this->owner();
        abort_unless($owner->hasStripeSubscription(), 404);
        $billing->cancelAtPeriodEnd($owner);

        return response()->json(['success' => true, 'message' => 'Auto-renew is off. You keep your plan until ' . $owner->fresh()->subscription_renews_at?->format('d M Y') . '.']);
    }

    /** Turn auto-renew back on */
    public function resumeSubscription(StripeBillingService $billing)
    {
        $owner = $this->owner();
        abort_unless($owner->hasStripeSubscription(), 404);
        $billing->resume($owner);

        return response()->json(['success' => true, 'message' => 'Auto-renew is back on.']);
    }

    /** Stripe billing portal link (update the card, Stripe's own invoices) */
    public function billingPortal(StripeBillingService $billing)
    {
        $owner = $this->owner();
        abort_unless(StripeBillingService::enabled() && $owner->stripe_customer_id, 404);

        try {
            return response()->json(['url' => $billing->billingPortalUrl($owner)]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            StripeBillingService::logError($e, 'billing portal');

            return response()->json(['message' => 'Billing portal is unavailable right now. ' . $e->getMessage()], 422);
        }
    }

    /**
     * Checkout page data
     *
     * The order for a first subscription paid on our own checkout page, plus the Stripe publishable key.
     *
     * @queryParam plan_id integer required Example: 3
     * @queryParam interval string Example: monthly
     * @queryParam coupon_code string Example: WELCOME10
     */
    public function checkout(Request $request)
    {
        [$owner, $plan, $interval, $pricing] = $this->checkoutTarget($request);
        $price = $plan->priceFor($interval);
        $total = $pricing['final'] ?? $price;
        $coupon = $pricing['coupon'] ?? null;

        return response()->json([
            'plan' => ['id' => $plan->id, 'name' => $plan->getTranslation('name')],
            'interval' => $interval,
            'unit' => $interval === 'yearly' ? 'year' : 'month',
            'price' => (float) $price,
            'total' => (float) $total,
            'coupon' => $coupon ? [
                'code' => $coupon->code,
                'label' => $coupon->discountLabel() . ' ' . $coupon->durationLabel(),
                'discount' => (float) ($pricing['discount'] ?? 0),
            ] : null,
            // Coupons that repeat keep discounting the renewals; a one-time coupon only covers today.
            'renew_amount' => (float) ($coupon && $coupon->duration !== 'once' ? $total : $price),
            'renews_on' => now()->add($interval === 'yearly' ? '1 year' : '1 month')->format('d M Y'),
            'highlights' => collect($plan->entitlementLines())->filter(fn ($l) => $l[1])->take(3)->pluck(0)->values(),
            'owner' => ['name' => $owner->displayName(), 'email' => $owner->email],
            'stripe_key' => config('services.stripe.key'),
            'currency' => strtoupper(config('services.stripe.currency', 'aed')),
        ]);
    }

    /** Checkout step 1: the unpaid subscription + the client secret the card fields confirm */
    public function checkoutIntent(Request $request, StripeBillingService $billing)
    {
        [$owner, $plan, $interval, $pricing] = $this->checkoutTarget($request);

        try {
            return response()->json(['success' => true] + $billing->createSubscriptionIntent($owner, $plan, $interval, $pricing));
        } catch (\Stripe\Exception\ApiErrorException $e) {
            StripeBillingService::logError($e, 'checkout intent');

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Checkout step 2
     *
     * After the card payment is confirmed: activates the plan (the webhook does the same).
     *
     * @bodyParam subscription_id string required Example: sub_123
     */
    public function checkoutComplete(Request $request, StripeBillingService $billing)
    {
        $request->validate(['subscription_id' => 'required|string|starts_with:sub_']);
        $subscriptionId = $request->input('subscription_id');

        $upgrade = PlanUpgradeRequest::where('stripe_subscription_id', $subscriptionId)
            ->where('portal_user_id', $this->owner()->id)->first();
        abort_unless($upgrade && StripeBillingService::enabled(), 404);

        try {
            $upgrade = $billing->fulfillSubscription($subscriptionId) ?? $upgrade;
            $card = $billing->subscriptionCard($subscriptionId);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            StripeBillingService::logError($e, 'checkout completion');
            $card = null;
        }

        return response()->json([
            'success' => true,
            'activated' => $upgrade->status === 'approved',
            'plan' => $upgrade->plan->getTranslation('name'),
            'card' => $card,
        ]);
    }

    /**
     * Finish a Stripe-hosted checkout
     *
     * Stripe sends the browser back to the Plans page with `session_id`; this activates the plan
     * right away (the webhook does the same).
     *
     * @bodyParam session_id string required Example: cs_123
     */
    public function checkoutSession(Request $request, StripeBillingService $billing)
    {
        $request->validate(['session_id' => 'required|string|starts_with:cs_']);
        $sessionId = $request->input('session_id');
        abort_unless(StripeBillingService::enabled(), 404);

        // Only the account that started this checkout may complete it.
        $owns = PlanUpgradeRequest::where('stripe_checkout_session_id', $sessionId)
            ->where('portal_user_id', $this->owner()->id)->exists();
        abort_unless($owns, 404);

        try {
            $upgrade = $billing->fulfillCheckout($sessionId);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            StripeBillingService::logError($e, 'checkout fulfilment');
            $upgrade = null;
        }

        return response()->json(['success' => true, 'message' => $upgrade
            ? 'Payment successful — welcome to the ' . $upgrade->plan->getTranslation('name') . ' plan!'
            : "Payment received — we're confirming it with Stripe. Your plan will update in a moment."]);
    }

    /**
     * Payment history
     *
     * The last 12 months, newest first, grouped by month, with totals.
     */
    public function payments()
    {
        $owner = $this->owner();
        $since = now()->subYear()->startOfDay();

        $payments = $owner->planPayments()
            ->where('paid_at', '>=', $since)
            ->orderByDesc('paid_at')->orderByDesc('id')
            ->get();

        return response()->json([
            'since' => $since->toIso8601String(),
            'summary' => [
                'total' => (float) $payments->sum('amount'),
                'count' => $payments->count(),
                'saved' => (float) $payments->sum('discount_amount'),
                'last' => $payments->first()?->paid_at?->toIso8601String(),
            ],
            'months' => $payments->groupBy(fn ($p) => $p->paid_at->format('Y-m'))
                ->map(fn ($rows, $month) => [
                    'month' => $month,
                    'label' => \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('F Y'),
                    'payments' => $rows->map(fn (PlanPayment $p) => [
                        'id' => $p->id,
                        'paid_at' => $p->paid_at->toIso8601String(),
                        'plan' => $p->plan_name,
                        'cycle' => ucfirst(str_replace('_', ' ', (string) $p->billing_cycle)),
                        'method' => $p->stripe_invoice_id ? 'Card payment' : 'Recorded by MW Realty',
                        'coupon_code' => $p->coupon_code,
                        'discount' => (float) $p->discount_amount,
                        'amount' => (float) $p->amount,
                        'original_amount' => $p->original_amount ? (float) $p->original_amount : null,
                        'paid' => $p->isPaid(),
                        'failure_reason' => $p->failure_reason,
                    ])->values(),
                ])->values(),
        ]);
    }

    /**
     * One invoice
     *
     * The invoice document as HTML (the same one the PDF and the invoice email use).
     */
    public function invoice($id, InvoiceService $invoices)
    {
        $payment = $this->owner()->planPayments()->findOrFail($id);
        $data = $invoices->data($payment);

        return response()->json([
            'number' => $data['number'],
            'html' => view('invoices._styles')->render() . view('invoices._document', $data)->render(),
        ]);
    }

    /** Download an invoice as PDF */
    public function invoicePdf($id, InvoiceService $invoices)
    {
        $payment = $this->owner()->planPayments()->findOrFail($id);

        return $invoices->pdf($payment)->download(InvoiceService::filename($payment));
    }

    /** Agent / company only — Super Admin doesn't buy plans for itself. */
    private function owner(): PortalUser
    {
        $owner = Auth::guard('portal')->user();
        abort_unless($owner, 403, 'Plans belong to agent and company accounts.');

        return $owner;
    }

    /** Resolves plan + interval + optional coupon from a plan-change request (shared by preview / submit / checkout). */
    private function resolveChange(Request $request): array
    {
        $request->validate([
            'plan_id' => 'required|integer|exists:plans,id',
            'interval' => 'nullable|in:monthly,yearly',
            'coupon_code' => 'nullable|string|max:40',
        ]);

        $owner = $this->owner();
        if ($owner->isOnAgencyPlan()) {
            throw ValidationException::withMessages(['plan_id' => "You're on {$owner->company->displayName()}'s plan while you're a member of the agency — there's nothing to buy."]);
        }
        $plan = Plan::findOrFail($request->input('plan_id'));
        $interval = $request->input('interval') === 'yearly' && $plan->hasYearly() ? 'yearly' : 'monthly';
        $pricing = $request->filled('coupon_code') && (float) $plan->price > 0
            ? app(CouponService::class)->check($request->input('coupon_code'), $owner, $plan, $interval)
            : null;

        return [$owner, $plan, $interval, $pricing];
    }

    /** Plan + interval + coupon for a first online subscription, or 4xx when it can't be bought here. */
    private function checkoutTarget(Request $request): array
    {
        [$owner, $plan, $interval, $pricing] = $this->resolveChange($request);

        abort_unless(StripeBillingService::supportsPlan($plan, $interval), 404);
        abort_if($owner->hasStripeSubscription(), 422, 'You already have a subscription — change plans from the Plans page.');
        abort_if($owner->hasPendingPlanUpgradeRequest(), 422, 'You already have a pending plan request.');

        return [$owner, $plan, $interval, $pricing];
    }

    /** One plan card: price + note per interval, features, and what its button does per interval. */
    private function planCard(Plan $plan, int $index, PortalUser $owner, ?StripeBillingService $billing, bool $subscribed, ?PlanUpgradeRequest $pendingRequest): array
    {
        $isCurrentPlan = $owner->plan_id === $plan->id;
        $isFree = (float) $plan->price <= 0;
        $currentInterval = $owner->billing_interval ?: 'monthly';
        $lines = collect($plan->entitlementLines());

        $intervals = [];
        foreach (Plan::INTERVALS as $interval) {
            // A plan without a yearly price keeps showing (and selling) monthly under the Yearly tab.
            $effective = $interval === 'yearly' && !$plan->hasYearly() ? 'monthly' : $interval;
            $isCurrent = $isCurrentPlan && ($isFree || !$subscribed || $effective === $currentInterval);
            $isScheduled = $subscribed && $owner->scheduled_plan_id === $plan->id && ($owner->scheduled_interval ?: 'monthly') === $effective;
            $online = StripeBillingService::supportsPlan($plan, $effective);
            $change = $subscribed && ($online || $isFree) ? $billing->classifyChange($owner, $plan, $effective) : null;
            $isPending = $pendingRequest && $pendingRequest->plan_id === $plan->id;

            $action = match (true) {
                $isCurrent => ['kind' => 'current'],
                $isScheduled || ($isFree && $subscribed && $owner->subscription_cancel_at_period_end) => ['kind' => 'scheduled', 'starts' => $owner->subscription_renews_at?->format('d M')],
                $change && !$pendingRequest => ['kind' => 'change', 'change' => $change],
                $online && !$subscribed && !$pendingRequest => ['kind' => 'checkout'],
                (bool) $isPending => ['kind' => 'pending'],
                (bool) $pendingRequest => ['kind' => 'blocked'],
                default => ['kind' => 'request', 'label' => $owner->plan && $plan->price < $owner->plan->price ? 'Switch Plan' : 'Upgrade Now'],
            };

            $yearlyNote = null;
            if ($interval === 'yearly' && !$isFree) {
                $yearlyNote = $plan->hasYearly()
                    ? ['text' => '≈ AED ' . number_format($plan->yearly_price / 12, 0) . '/month' . ($plan->yearlySavingsPercent() > 0 ? ' · save ' . $plan->yearlySavingsPercent() . '%' : ''), 'muted' => false]
                    : ['text' => 'Monthly billing only', 'muted' => true];
            }

            $intervals[$interval] = [
                'effective' => $effective,
                'price' => $plan->priceFor($effective),
                'unit' => $effective === 'yearly' ? 'year' : 'month',
                'yearly_note' => $yearlyNote,
                'action' => $action,
            ];
        }

        return [
            'id' => $plan->id,
            'name' => $plan->getTranslation('name'),
            'description' => $plan->getTranslation('description'),
            'popular' => (bool) $plan->is_popular,
            'is_current_plan' => $isCurrentPlan,
            'is_free' => $isFree,
            'icon' => self::TIER_ICONS[min($index, count(self::TIER_ICONS) - 1)],
            // Included entitlements (the plan's first line stands out), its own features, then what's not included.
            'features' => $lines->filter(fn ($l) => $l[1])->map(fn ($l, $i) => ['text' => $l[0], 'state' => $i === 0 ? 'highlight' : 'included'])->values()
                ->concat(collect($plan->features ?? [])->map(fn ($f) => ['text' => $f, 'state' => 'included']))
                ->concat($lines->reject(fn ($l) => $l[1])->map(fn ($l) => ['text' => $l[0], 'state' => 'excluded']))
                ->values(),
            'intervals' => $intervals,
        ];
    }
}
