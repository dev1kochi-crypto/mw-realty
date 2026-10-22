<?php

namespace App\Services;

use App\Models\CreditRate;
use App\Models\CreditSetting;
use App\Models\CreditTransaction;
use App\Models\PortalUser;
use App\Models\Property;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Credits — the pay-as-you-go add-ons next to a plan. An account buys credits (Top Up, card via
 * Stripe) or is granted them by Super Admin; each batch expires after credit_settings.validity_days.
 * They're spent on an extra listing once the plan's allowance is used up, or on Premium for 7 / 15 /
 * 30 days without using the plan's premium quota. Costs come from credit_rates (the calculator).
 *
 * The wallet belongs to the account that owns the listings: an agency agent spends the agency's
 * credits (PortalUser::listingOwner()). Spending draws the batches that expire first.
 */
class CreditService
{
    /**
     * The Admin › Credits on/off switch. Off only stops *adding* credits (buying them): an account
     * that still holds credits keeps seeing them and spending them until they expire (usableBy()).
     */
    public static function enabled(): bool
    {
        return CreditSetting::current()->enabled;
    }

    /** Whether $account has credits left to spend. */
    public static function hasCredits(PortalUser $account): bool
    {
        return CreditTransaction::where('account_id', $account->id)->spendable()->exists();
    }

    /**
     * Whether credits show in the CRM for this account (its wallet's): always while credits are on,
     * and while they're off only if the wallet still holds credits. Super Admin (null) → only when on.
     */
    public static function shownTo(?PortalUser $user): bool
    {
        return static::enabled() || ($user && static::hasCredits($user->listingOwner()));
    }

    /** The account whose credits $user's listings draw on (the agency's for an agency agent). */
    public function wallet(PortalUser $user): PortalUser
    {
        return $user->listingOwner();
    }

    /**
     * Whether $user buys and spends credits themselves. Like the plan, credits belong to the
     * account that owns the listings: an agent inside an agency has none of their own — the agency
     * buys and uses them, and the agent asks the agency. With credits switched off, only an account
     * that still holds credits can use them (no buying — see canBuy()).
     */
    public static function usableBy(?PortalUser $user): bool
    {
        return $user && $user->isApproved() && $user->listingOwner()->id === $user->id
            && (static::enabled() || static::hasCredits($user));
    }

    /** Whether $user can add credits (buy them) — only while credits are switched on. */
    public static function canBuy(?PortalUser $user): bool
    {
        return static::enabled() && static::usableBy($user);
    }

    public function balance(PortalUser $account): int
    {
        return (int) CreditTransaction::where('account_id', $account->id)->spendable()->sum('remaining');
    }

    /** The batch that expires next: ['credits', 'expires_at'] or null. */
    public function nextExpiry(PortalUser $account): ?array
    {
        $lot = CreditTransaction::where('account_id', $account->id)->spendable()->whereNotNull('expires_at')->orderBy('expires_at')->first();
        if (!$lot) {
            return null;
        }

        return [
            'credits' => (int) CreditTransaction::where('account_id', $account->id)->spendable()->where('expires_at', $lot->expires_at)->sum('remaining'),
            'expires_at' => $lot->expires_at,
        ];
    }

    /**
     * The wallet at a glance (sidebar meter): balance, the credits bought / given that are still
     * within their validity (`active` — so balance ÷ active shows how much has been used), and
     * the next expiry.
     */
    public function summary(PortalUser $account): array
    {
        // Read-only: balance() already ignores credits past their expiry.
        $active = (int) CreditTransaction::where('account_id', $account->id)->where('credits', '>', 0)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->sum('credits');
        $balance = $this->balance($account);
        $expiry = $this->nextExpiry($account);

        return [
            'balance' => $balance,
            'active' => max($active, $balance),
            'percent' => $active > 0 ? (int) round($balance / max($active, $balance) * 100) : 0,
            'expiring' => $expiry ? [
                'credits' => $expiry['credits'],
                'expires_at' => $expiry['expires_at']->toIso8601String(),
                'days' => max(0, (int) ceil(now()->floatDiffInDays($expiry['expires_at']))),
            ] : null,
        ];
    }

    /** The rate for a saved listing, or a 422 when Super Admin hasn't priced listings like it. */
    public function rateFor(Property $property): CreditRate
    {
        return $this->rate($property->segment, $property->listing_type, $property->property_type, $property->price);
    }

    public function rate(?string $segment, ?string $listingType, ?string $propertyType, $price): CreditRate
    {
        return CreditRate::forListing($segment, $listingType, $propertyType, $price)
            ?? throw ValidationException::withMessages(['credits' => 'Credits aren\'t available for this kind of listing yet. Please contact MW Realty.']);
    }

    /**
     * Adds a batch of credits (top-up, Super Admin grant, refund) that expires after the validity
     * period. $actor: the portal user who bought it, an admin name, or null for System.
     */
    public function credit(PortalUser $account, int $credits, string $type, string $description, PortalUser|string|null $actor = null, array $extra = []): CreditTransaction
    {
        return DB::transaction(function () use ($account, $credits, $type, $description, $actor, $extra) {
            PortalUser::whereKey($account->id)->lockForUpdate()->first();
            $this->expireDue($account);

            return CreditTransaction::create([
                'account_id' => $account->id,
                'type' => $type,
                'credits' => $credits,
                'remaining' => $credits,
                'balance_after' => $this->balance($account) + $credits,
                'expires_at' => now()->addDays(CreditSetting::current()->validity_days)->endOfDay(),
                'description' => $description,
            ] + $this->actorFields($actor) + $extra);
        });
    }

    /**
     * Spends credits on an add-on for $property, oldest-expiring batches first. 422 when the wallet
     * doesn't have enough. Runs inside the caller's transaction when there is one.
     */
    public function spend(PortalUser $actor, int $credits, string $kind, string $description, ?Property $property = null): CreditTransaction
    {
        $account = $this->wallet($actor);

        return DB::transaction(function () use ($account, $actor, $credits, $kind, $description, $property) {
            // One spend at a time per wallet, so two can't both use the last credits.
            PortalUser::whereKey($account->id)->lockForUpdate()->first();
            $this->expireDue($account);

            $balance = $this->balance($account);
            if ($credits > $balance) {
                throw ValidationException::withMessages(['credits' => "This needs {$credits} credits but you have " . number_format($balance) . '.' . (static::enabled() ? ' Top up to continue.' : '')]);
            }

            $left = $credits;
            $lots = CreditTransaction::where('account_id', $account->id)->spendable()
                ->orderByRaw('expires_at IS NULL')->orderBy('expires_at')->orderBy('id')->lockForUpdate()->get();
            foreach ($lots as $lot) {
                if ($left <= 0) {
                    break;
                }
                $take = min($left, $lot->remaining);
                $lot->decrement('remaining', $take);
                $left -= $take;
            }

            return CreditTransaction::create([
                'account_id' => $account->id,
                'type' => CreditTransaction::SPEND,
                'kind' => $kind,
                'credits' => -$credits,
                'balance_after' => $balance - $credits,
                'description' => $description,
            ] + $this->actorFields($actor) + ($property ? $this->propertyFields($property) : []));
        });
    }

    /** Gives a spend back as a new batch (e.g. a credit-paid premium cancelled before it started). */
    public function refund(CreditTransaction $spend, string $description): CreditTransaction
    {
        return $this->credit($spend->account, abs($spend->credits), CreditTransaction::REFUND, $description, null, [
            'kind' => $spend->kind,
            'property_id' => $spend->property_id,
        ] + $spend->only(['reference', 'property_type', 'category', 'location']));
    }

    /** Links a spend to the listing it paid for, once the listing exists (extra listing). */
    public function attach(CreditTransaction $spend, Property $property): void
    {
        $spend->update($this->propertyFields($property));
    }

    /**
     * Writes off batches past their expiry date: one "Expired credits" row per account. Pass an
     * account to do just that one (callers hold its lock); null sweeps every account (scheduler).
     */
    public function expireDue(?PortalUser $account = null): int
    {
        $due = CreditTransaction::where('credits', '>', 0)->where('remaining', '>', 0)
            ->whereNotNull('expires_at')->where('expires_at', '<=', now())
            ->when($account, fn ($q) => $q->where('account_id', $account->id));

        if (!$account) {
            $expired = 0;
            foreach ((clone $due)->distinct()->pluck('account_id') as $accountId) {
                $expired += DB::transaction(function () use ($accountId) {
                    $owner = PortalUser::whereKey($accountId)->lockForUpdate()->first();

                    return $owner ? $this->expireDue($owner) : 0;
                });
            }

            return $expired;
        }

        $lots = $due->lockForUpdate()->get();
        $credits = (int) $lots->sum('remaining');
        if ($credits === 0) {
            return 0;
        }
        CreditTransaction::whereIn('id', $lots->pluck('id'))->update(['remaining' => 0]);
        CreditTransaction::create([
            'account_id' => $account->id,
            'type' => CreditTransaction::EXPIRE,
            'credits' => -$credits,
            'balance_after' => $this->balance($account),
            'description' => 'Expired credits',
        ] + $this->actorFields(null));

        return $credits;
    }

    // ------------------------------------------------------------------ top up (Stripe)

    /** Validates a top-up quantity against Super Admin's min / max. */
    public function assertTopUpQuantity(int $credits): void
    {
        $settings = CreditSetting::current();
        if ($credits < $settings->min_topup || $credits > $settings->max_topup) {
            throw ValidationException::withMessages(['credits' => 'Top up between ' . number_format($settings->min_topup) . ' and ' . number_format($settings->max_topup) . ' credits.']);
        }
    }

    /** Creates the card payment for a top-up: ['payment_intent_id', 'client_secret', 'total']. */
    public function createTopUpIntent(PortalUser $buyer, int $credits): array
    {
        $this->assertTopUpQuantity($credits);
        $account = $this->wallet($buyer);
        $price = CreditSetting::current()->priceFor($credits);
        $billing = app(StripeBillingService::class);

        $intent = $billing->client()->paymentIntents->create([
            'amount' => (int) round($price['total'] * 100),
            'currency' => strtolower(config('services.stripe.currency', 'aed')),
            'customer' => $billing->ensureCustomer($account),
            'payment_method_types' => ['card'],
            'description' => number_format($credits) . ' MW Realty credits',
            'metadata' => [
                'kind' => 'credit_topup',
                'account_id' => $account->id,
                'buyer_id' => $buyer->id,
                'credits' => $credits,
                'subtotal' => $price['subtotal'],
                'vat' => $price['vat'],
            ],
        ]);

        return ['payment_intent_id' => $intent->id, 'client_secret' => $intent->client_secret, 'total' => $price['total']];
    }

    /**
     * Adds the credits for a paid top-up (the page after the card is confirmed, and the
     * payment_intent.succeeded webhook). Safe to call repeatedly; null while it isn't paid.
     */
    public function fulfillTopUp(string $paymentIntentId): ?CreditTransaction
    {
        $intent = app(StripeBillingService::class)->client()->paymentIntents->retrieve($paymentIntentId);
        if ($intent->status !== 'succeeded' || ($intent->metadata->kind ?? null) !== 'credit_topup') {
            return null;
        }

        $account = PortalUser::find((int) $intent->metadata->account_id);
        if (!$account) {
            return null;
        }

        return DB::transaction(function () use ($intent, $account) {
            PortalUser::whereKey($account->id)->lockForUpdate()->first();
            if ($done = CreditTransaction::where('stripe_payment_intent_id', $intent->id)->first()) {
                return $done;
            }
            $credits = (int) $intent->metadata->credits;

            return $this->credit($account, $credits, CreditTransaction::TOPUP, 'Credit addition', PortalUser::find((int) $intent->metadata->buyer_id), [
                'amount' => (float) $intent->metadata->subtotal,
                'vat' => (float) $intent->metadata->vat,
                'total' => round($intent->amount_received / 100, 2),
                'stripe_payment_intent_id' => $intent->id,
            ]);
        });
    }

    // ------------------------------------------------------------------ helpers

    /** Snapshot of the listing for the history (it may change or be deleted later). */
    public function propertyFields(Property $property): array
    {
        $segment = $property->segment === 'commercial' ? 'Commercial' : 'Residential';
        $type = $property->listing_type ? ($property->filterLabel('listing_type') ?: ucfirst($property->listing_type)) : null;

        return [
            'property_id' => $property->id,
            'reference' => $property->reference_no,
            'property_type' => $property->property_type ? ($property->filterLabel('property_type') ?: ucfirst($property->property_type)) : null,
            'category' => $segment . ($type ? ' for ' . $type : ''),
            'location' => collect(['city', 'community'])->map(fn ($f) => $property->getTranslation($f))->filter()->unique()->implode(', ') ?: null,
        ];
    }

    private function actorFields(PortalUser|string|null $actor): array
    {
        return match (true) {
            $actor instanceof PortalUser => ['actor_type' => 'portal', 'actor_id' => $actor->id, 'actor_name' => $actor->displayName()],
            is_string($actor) => ['actor_type' => 'admin', 'actor_id' => null, 'actor_name' => $actor],
            default => ['actor_type' => 'system', 'actor_id' => null, 'actor_name' => 'System'],
        };
    }
}
