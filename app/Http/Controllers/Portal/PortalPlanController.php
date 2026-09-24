<?php

namespace App\Http\Controllers\Portal;

use App\Models\CmsKit\Admin;
use App\Models\Plan;
use App\Models\PlanUpgradeRequest;
use App\Notifications\PlanUpgradeRequestedNotification;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Services\CouponService;
use App\Services\StripeBillingService;

class PortalPlanController extends Controller
{
    public function index()
    {
        $owner = Auth::guard('portal')->user();
        $stripeEnabled = StripeBillingService::enabled();
        if ($stripeEnabled) {
            app(StripeBillingService::class)->applyDueScheduledChange($owner);
            $owner->refresh();
        }
        $plans = Plan::active()->orderBy('order_index')->get();
        $pendingRequest = $owner->planUpgradeRequests()->pending()->latest()->first();
        $hasPayments = $owner->planPayments()->exists();
        $hasYearly = $plans->contains(fn ($p) => $p->hasYearly());

        return view('portal.plans.index', compact('plans', 'owner', 'pendingRequest', 'stripeEnabled', 'hasPayments', 'hasYearly'));
    }

    /** One invoice — only the logged-in account's own payments. */
    public function invoice($id, \App\Services\InvoiceService $invoices)
    {
        $payment = Auth::guard('portal')->user()->planPayments()->findOrFail($id);

        return view('portal.plans.invoice', $invoices->data($payment));
    }

    public function invoicePdf($id, \App\Services\InvoiceService $invoices)
    {
        $payment = Auth::guard('portal')->user()->planPayments()->findOrFail($id);

        return $invoices->pdf($payment)->download(\App\Services\InvoiceService::filename($payment));
    }

    /** Resolves plan + interval + optional coupon from a plan-change request (shared by preview/submit). */
    private function resolveChange(Request $request): array
    {
        $request->validate([
            'plan_id' => 'required|integer|exists:plans,id',
            'interval' => 'nullable|in:monthly,yearly',
            'coupon_code' => 'nullable|string|max:40',
        ]);

        $owner = Auth::guard('portal')->user();
        $plan = Plan::findOrFail($request->input('plan_id'));
        $interval = $request->input('interval') === 'yearly' && $plan->hasYearly() ? 'yearly' : 'monthly';
        $pricing = $request->filled('coupon_code') && (float) $plan->price > 0
            ? app(CouponService::class)->check($request->input('coupon_code'), $owner, $plan, $interval)
            : null;

        return [$owner, $plan, $interval, $pricing];
    }

    /** "What will this switch cost?" — shown in the confirmation modal before changing plan. */
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

    /** Undo a scheduled downgrade (keep the current plan after renewal). */
    public function keepCurrentPlan(StripeBillingService $billing)
    {
        $owner = Auth::guard('portal')->user();
        abort_unless($owner->hasStripeSubscription(), 404);
        $billing->keepCurrentPlan($owner);

        return back()->with('success', 'Scheduled change cancelled — you stay on ' . $owner->plan?->getTranslation('name') . '.');
    }

    /** Payment History page — the last 12 months, grouped by month. */
    public function payments()
    {
        $owner = Auth::guard('portal')->user();
        $since = now()->subYear()->startOfDay();

        $payments = $owner->planPayments()
            ->where('paid_at', '>=', $since)
            ->orderByDesc('paid_at')->orderByDesc('id')
            ->get();

        return view('portal.plans.payments', [
            'owner' => $owner,
            'since' => $since,
            'months' => $payments->groupBy(fn ($p) => $p->paid_at->format('Y-m')),
            'summary' => [
                'total' => (float) $payments->sum('amount'),
                'count' => $payments->count(),
                'saved' => (float) $payments->sum('discount_amount'),
                'last' => $payments->first(),
            ],
            'stripeEnabled' => StripeBillingService::enabled(),
        ]);
    }

    public function request(Request $request)
    {
        try {
            [$owner, $plan, $interval, $pricing] = $this->resolveChange($request);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        abort_if($owner->hasPendingPlanUpgradeRequest(), 422, 'You already have a pending plan request.');

        // Online payment: new subscription → Stripe Checkout; existing one → upgrade now /
        // downgrade at renewal / cancel at period end (see StripeBillingService::classifyChange()).
        if (StripeBillingService::enabled()) {
            $billing = app(StripeBillingService::class);
            try {
                if ($owner->hasStripeSubscription()) {
                    if ((float) $plan->price <= 0 || StripeBillingService::supportsPlan($plan, $interval)) {
                        return back()->with('success', $billing->changePlan($owner, $plan, $interval, $pricing, $request->integer('proration_date') ?: null));
                    }
                } elseif (StripeBillingService::supportsPlan($plan, $interval)) {
                    return redirect()->away($billing->createCheckout($owner, $plan, $interval, $pricing));
                }
            } catch (\Stripe\Exception\ApiErrorException $e) {
                StripeBillingService::logError($e, 'plan change');
                return back()->with('error', 'Payment could not be completed: ' . $e->getMessage());
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

        return back()->with('success', 'Upgrade request submitted' . ($pricing ? ' with coupon ' . $pricing['coupon']->code : '') . ' — Super Admin will review it shortly.');
    }

    /** Stripe Checkout success redirect — activates the plan right away (the webhook does the same). */
    public function checkoutSuccess(Request $request, StripeBillingService $billing)
    {
        $sessionId = (string) $request->query('session_id');
        abort_unless(StripeBillingService::enabled() && str_starts_with($sessionId, 'cs_'), 404);

        // Only the account that started this checkout may complete it.
        $owns = PlanUpgradeRequest::where('stripe_checkout_session_id', $sessionId)
            ->where('portal_user_id', Auth::guard('portal')->id())->exists();
        abort_unless($owns, 404);

        try {
            $upgrade = $billing->fulfillCheckout($sessionId);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            StripeBillingService::logError($e, 'checkout fulfilment');
            $upgrade = null;
        }

        return $upgrade
            ? redirect()->route('portal.plans.index')->with('success', 'Payment successful — welcome to the ' . $upgrade->plan->getTranslation('name') . ' plan!')
            : redirect()->route('portal.plans.index')->with('success', "Payment received — we're confirming it with Stripe. Your plan will update in a moment.");
    }

    public function cancelSubscription(StripeBillingService $billing)
    {
        $owner = Auth::guard('portal')->user();
        abort_unless($owner->hasStripeSubscription(), 404);
        $billing->cancelAtPeriodEnd($owner);

        return back()->with('success', 'Auto-renew is off. You keep your plan until ' . $owner->fresh()->subscription_renews_at?->format('d M Y') . '.');
    }

    public function resumeSubscription(StripeBillingService $billing)
    {
        $owner = Auth::guard('portal')->user();
        abort_unless($owner->hasStripeSubscription(), 404);
        $billing->resume($owner);

        return back()->with('success', 'Auto-renew is back on.');
    }

    public function billingPortal(StripeBillingService $billing)
    {
        $owner = Auth::guard('portal')->user();
        abort_unless(StripeBillingService::enabled() && $owner->stripe_customer_id, 404);

        try {
            return redirect()->away($billing->billingPortalUrl($owner));
        } catch (\Stripe\Exception\ApiErrorException $e) {
            StripeBillingService::logError($e, 'billing portal');
            return back()->with('error', 'Billing portal is unavailable right now. ' . $e->getMessage());
        }
    }

    /** Live coupon preview for the Plans page: discounted price per plan, or why it doesn't apply. */
    public function checkCoupon(Request $request)
    {
        $request->validate(['coupon_code' => 'required|string|max:40']);
        $owner = Auth::guard('portal')->user();
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
}
