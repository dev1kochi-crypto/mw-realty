<?php

namespace App\Http\Controllers;

use App\Services\StripeBillingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * POST /stripe/webhook — signature-verified Stripe events that keep plan subscriptions in sync.
 * Every handler is idempotent (Stripe retries and may deliver events more than once).
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeBillingService $billing)
    {
        $secret = config('services.stripe.webhook_secret');
        abort_unless($secret, 404);

        try {
            $event = Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), $secret);
        } catch (SignatureVerificationException|\UnexpectedValueException $e) {
            Log::warning('Stripe webhook rejected: ' . $e->getMessage());
            return response('Invalid signature', 400);
        }

        $object = $event->data->object;

        try {
            match ($event->type) {
                'checkout.session.completed', 'checkout.session.async_payment_succeeded' => $billing->fulfillCheckout($object->id),
                'invoice.paid' => $billing->recordInvoice($object),
                'invoice.payment_failed' => $billing->markPaymentFailed($object),
                'customer.subscription.created', 'customer.subscription.updated' => $billing->applySubscription(null, $object),
                'customer.subscription.deleted' => $this->subscriptionDeleted($billing, $object),
                default => null,
            };
        } catch (\Throwable $e) {
            StripeBillingService::logError($e, "webhook {$event->type} ({$event->id})");
            return response('Handler error', 500); // Stripe retries
        }

        return response('OK', 200);
    }

    private function subscriptionDeleted(StripeBillingService $billing, $subscription): void
    {
        $user = \App\Models\PortalUser::where('stripe_subscription_id', $subscription->id)->first()
            ?? \App\Models\PortalUser::where('stripe_customer_id', $subscription->customer)->first();

        if ($user) {
            $billing->endSubscription($user, $subscription->id);
        }
    }
}
