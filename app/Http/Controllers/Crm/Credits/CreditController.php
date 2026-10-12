<?php

namespace App\Http\Controllers\Crm\Credits;

use App\Http\Controllers\Crm\Concerns\ScopesPortalOwner;
use App\Models\CreditRate;
use App\Models\CreditSetting;
use App\Models\CreditTransaction;
use App\Models\Filter;
use App\Models\PortalUser;
use App\Models\Property;
use App\Services\CreditService;
use App\Services\StripeBillingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

/**
 * @group CRM Credits
 *
 * Credits — add-ons next to the plan: an extra listing once the plan's allowance is used up, or
 * Premium for 7 / 15 / 30 days without using the plan's premium quota. Bought with Top Up (card, via
 * Stripe: POST /credits/top-up/intent → confirm the card with Stripe → POST /credits/top-up/complete),
 * valid for a set number of days. An agency's agents spend the agency's credits. See CreditService.
 */
class CreditController extends Controller
{
    use ScopesPortalOwner;

    public function __construct(private readonly CreditService $credits)
    {
    }

    /**
     * Credit usage history
     *
     * The wallet (balance, next expiry, top-up pricing) and its transactions, newest first, 20 per page.
     * Super Admin sees every account's.
     *
     * @queryParam q string Reference search. Example: RERA1024
     * @queryParam from string Y-m-d, default a month ago. Example: 2026-09-12
     * @queryParam to string Y-m-d, default today. Example: 2026-10-12
     * @queryParam page integer Example: 1
     */
    public function index(Request $request)
    {
        [$from, $to] = $this->range($request);
        $wallet = $this->walletAccount();
        // An agency agent has no credits of their own: the agency buys and uses them.
        abort_if($wallet && $wallet->id !== $this->owner()->id, 403, 'Credits are managed by your agency, ' . $wallet?->displayName() . '.');
        if ($wallet) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($wallet) {
                PortalUser::whereKey($wallet->id)->lockForUpdate()->first();
                $this->credits->expireDue($wallet);
            });
        }

        $page = $this->query($request, $from, $to)->with('account:id,type,name,company_name', 'actor:id,avatar', 'property:id,segment')->paginate(20);
        $settings = CreditSetting::current();
        // Totals for the chosen dates (ignoring the reference search): credits in, used on add-ons, expired.
        $totals = CreditTransaction::query()
            ->when(!$this->isAdmin(), fn ($q) => $q->where('account_id', $wallet?->id ?? 0))
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('COALESCE(SUM(CASE WHEN credits > 0 THEN credits ELSE 0 END), 0) AS added')
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'spend' THEN -credits ELSE 0 END), 0) AS used")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'expire' THEN -credits ELSE 0 END), 0) AS expired")
            ->first();

        return response()->json([
            'data' => collect($page->items())->map(fn (CreditTransaction $t) => $this->row($t)),
            'meta' => [
                'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(),
                'from' => $page->firstItem(), 'to' => $page->lastItem(),
            ],
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'is_admin' => $this->isAdmin(),
            'enabled' => $settings->enabled,
            'totals' => ['added' => (int) $totals->added, 'used' => (int) $totals->used, 'expired' => (int) $totals->expired],
            'plan' => $wallet ? $this->planUsage($wallet) : null,
            'wallet' => $wallet ? $this->credits->summary($wallet) + [
                'account' => $wallet->displayName(),
                'shared' => $wallet->id !== $this->owner()->id,
            ] : null,
            'top_up' => [
                // Only the account that owns the listings buys credits — never an agent inside an agency.
                'allowed' => CreditService::usableBy($this->owner()),
                'online' => StripeBillingService::enabled(),
                'price_per_credit' => (float) $settings->price_per_credit,
                'vat_percent' => (float) $settings->vat_percent,
                'validity_days' => $settings->validity_days,
                'valid_until' => now()->addDays($settings->validity_days)->toIso8601String(),
                'min' => $settings->min_topup,
                'max' => $settings->max_topup,
                'stripe_key' => config('services.stripe.key'),
                'currency' => strtoupper(config('services.stripe.currency', 'aed')),
            ],
        ]);
    }

    /** Export the history (same filters) as CSV */
    public function export(Request $request)
    {
        [$from, $to] = $this->range($request);
        $wallet = $this->walletAccount();
        abort_if($wallet && $wallet->id !== $this->owner()->id, 403, 'Credits are managed by your agency, ' . $wallet?->displayName() . '.');
        $query = $this->query($request, $from, $to)->with('account:id,type,name,company_name');
        $isAdmin = $this->isAdmin();

        return response()->streamDownload(function () use ($query, $isAdmin) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_filter(['Transaction Originator', $isAdmin ? 'Account' : null, 'Date', 'Credits', 'Credit Balance', 'Description',
                'Reference', 'Property Type', 'Category', 'Location', 'Amount paid (AED)'], fn ($v) => $v !== null));

            $query->chunk(500, function ($rows) use ($out, $isAdmin) {
                foreach ($rows as $t) {
                    fputcsv($out, array_values(array_filter([
                        $t->actor_name ?: 'System',
                        $isAdmin ? ($t->account?->displayName() ?? '') : false,
                        $t->created_at->format('Y-m-d H:i'),
                        $t->credits,
                        $t->balance_after,
                        $t->description,
                        $t->reference,
                        $t->property_type,
                        $t->category,
                        $t->location,
                        $t->total !== null ? (float) $t->total : '',
                    ], fn ($v) => $v !== false)));
                }
            });
            fclose($out);
        }, 'credit-usage-' . $from->format('Y-m-d') . '-to-' . $to->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Calculator options
     *
     * The choices for the credit calculator: categories, offering types and property types.
     */
    public function calculatorOptions()
    {
        $options = fn (string $key) => (Filter::where('key', $key)->with('activeValues')->first()?->activeValues ?? collect())
            ->map(fn ($v) => ['value' => (string) $v->value, 'label' => $v->getTranslation('label') ?: Str::headline((string) $v->value)])->values();

        return response()->json([
            'categories' => [['value' => 'residential', 'label' => 'Residential'], ['value' => 'commercial', 'label' => 'Commercial']],
            'listing_types' => $options('listing_type'),
            'property_types' => $options('property_type'),
            'premium_days' => array_keys(CreditRate::PREMIUM_DAYS),
        ]);
    }

    /**
     * Calculate credits
     *
     * What an extra listing and Premium (7 / 15 / 30 days) cost for a listing like this.
     *
     * @bodyParam segment string required residential | commercial. Example: residential
     * @bodyParam listing_type string required Example: rent
     * @bodyParam property_type string required Example: apartment
     * @bodyParam price number Example: 85000
     */
    public function calculate(Request $request)
    {
        $data = $request->validate([
            'segment' => 'required|in:residential,commercial',
            'listing_type' => 'required|string|max:50',
            'property_type' => 'required|string|max:50',
            'price' => 'nullable|numeric|min:0',
        ]);

        return response()->json($this->credits->rate($data['segment'], $data['listing_type'], $data['property_type'], $data['price'] ?? null)->costs());
    }

    /**
     * Quote for listings
     *
     * Credit costs for these listings (the "pay with credits" option when making them premium), plus the balance.
     *
     * @queryParam property_ids integer[] Example: [12, 14]
     */
    public function quote(Request $request)
    {
        $request->validate(['property_ids' => 'nullable|array|max:50', 'property_ids.*' => 'integer']);
        $viewer = $this->owner();
        abort_unless($viewer, 403, 'Credits belong to agent and company accounts.');

        $properties = Property::whereIn('id', $request->input('property_ids', []))->accessibleBy($viewer)->get();
        $costs = $properties->mapWithKeys(function (Property $p) {
            $rate = CreditRate::forListing($p->segment, $p->listing_type, $p->property_type, $p->price);

            return [$p->id => $rate?->costs()];
        });
        $priced = $costs->filter();

        return response()->json([
            'enabled' => CreditService::usableBy($viewer),
            'balance' => $this->credits->balance($this->credits->wallet($viewer)),
            'listings' => $costs,
            // Every listing priced? Then the total per premium length.
            'totals' => $priced->count() === $properties->count() && $properties->isNotEmpty()
                ? collect(CreditRate::PREMIUM_DAYS)->map(fn ($c, $days) => $priced->sum(fn ($cost) => $cost['premium'][$days]))->all()
                : null,
        ]);
    }

    /**
     * Top up: start the card payment
     *
     * Returns the Stripe client secret to confirm the card with, and the total charged.
     *
     * @bodyParam credits integer required Example: 1000
     */
    public function topUpIntent(Request $request)
    {
        $request->validate(['credits' => 'required|integer|min:1']);
        $buyer = $this->buyer();

        try {
            return response()->json(['success' => true] + $this->credits->createTopUpIntent($buyer, $request->integer('credits')));
        } catch (\Stripe\Exception\ApiErrorException $e) {
            StripeBillingService::logError($e, 'credit top-up intent');

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Top up: finish
     *
     * After the card payment is confirmed: adds the credits (the payment_intent.succeeded webhook does the same).
     *
     * @bodyParam payment_intent_id string required Example: pi_123
     */
    public function topUpComplete(Request $request)
    {
        $request->validate(['payment_intent_id' => 'required|string|starts_with:pi_']);
        $buyer = $this->buyer();

        try {
            $intent = app(StripeBillingService::class)->client()->paymentIntents->retrieve($request->input('payment_intent_id'));
            abort_unless(($intent->metadata->kind ?? null) === 'credit_topup' && (int) ($intent->metadata->account_id ?? 0) === $this->credits->wallet($buyer)->id, 404);
            $transaction = $this->credits->fulfillTopUp($intent->id);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            StripeBillingService::logError($e, 'credit top-up completion');
            $transaction = null;
        }

        return response()->json([
            'success' => true,
            'added' => (bool) $transaction,
            'balance' => $this->credits->balance($this->credits->wallet($buyer)),
            'message' => $transaction
                ? number_format($transaction->credits) . ' credits added — valid until ' . $transaction->expires_at->format('d M Y') . '.'
                : 'Payment received — we\'re confirming it with Stripe. Your credits will appear in a moment.',
        ]);
    }

    /**
     * The plan next to the credits: listings and premium used against the allowance, and when it
     * resets — credits pick up where these run out.
     */
    private function planUsage(PortalUser $account): array
    {
        $plan = $account->plan;
        $premium = app(\App\Services\FeaturedListingService::class)->quota($account);
        $perMonth = (bool) $plan?->listingsPerMonth();

        return [
            'name' => $plan?->getTranslation('name'),
            'listings' => [
                'used' => $account->usedPropertySlots(),
                'limit' => $plan && !$plan->isUnlimited() ? (int) $plan->property_limit : null,
                'remaining' => $account->remainingPropertySlots(),
                'per_month' => $perMonth,
            ],
            'premium' => $premium['limit'] > 0
                ? ['used' => $premium['used'], 'limit' => $premium['limit'], 'remaining' => $premium['remaining'], 'per_month' => $premium['per_month']]
                : null,
            'resets' => $perMonth || $premium['per_month'] ? now()->addMonthNoOverflow()->startOfMonth()->toIso8601String() : null,
        ];
    }

    /** The account whose credits the viewer sees and spends (null for Super Admin). */
    private function walletAccount(): ?PortalUser
    {
        $viewer = $this->owner();

        return $viewer ? $this->credits->wallet($viewer) : null;
    }

    /** The account buying credits (into its wallet — the agency's for an agency agent), with Stripe and credits switched on. */
    private function buyer(): PortalUser
    {
        $viewer = $this->owner();
        abort_unless($viewer && $viewer->isApproved(), 403, 'Credits belong to approved agent and company accounts.');
        abort_unless($this->credits->wallet($viewer)->id === $viewer->id, 403, 'Credits are managed by your agency — ask ' . $this->credits->wallet($viewer)->displayName() . ' to buy them.');
        abort_unless(CreditService::enabled() && StripeBillingService::enabled(), 422, 'Online top-up isn\'t available right now. Please contact MW Realty.');

        return $viewer;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(Request $request): array
    {
        $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d', 'q' => 'nullable|string|max:100']);
        $to = $request->filled('to') ? Carbon::createFromFormat('!Y-m-d', $request->input('to')) : today();
        $from = $request->filled('from') ? Carbon::createFromFormat('!Y-m-d', $request->input('from')) : $to->copy()->subMonth();
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from->startOfDay(), $to->endOfDay()];
    }

    private function query(Request $request, Carbon $from, Carbon $to)
    {
        $wallet = $this->walletAccount();
        $search = trim((string) $request->input('q', ''));

        return CreditTransaction::query()
            ->when(!$this->isAdmin(), fn ($q) => $q->where('account_id', $wallet?->id ?? 0))
            ->whereBetween('created_at', [$from, $to])
            ->when($search !== '', fn ($q) => $q->where('reference', 'like', '%' . addcslashes($search, '%_\\') . '%'))
            ->orderByDesc('created_at')->orderByDesc('id');
    }

    private function row(CreditTransaction $t): array
    {
        return [
            'id' => $t->id,
            'type' => $t->type,
            'kind' => $t->kind,
            'originator' => $t->actor_name ?: 'System',
            'avatar' => $t->actor_type === 'portal' && $t->actor?->avatar ? media_url($t->actor->avatar) : null,
            'account' => $this->isAdmin() ? $t->account?->displayName() : null,
            'date' => $t->created_at->toIso8601String(),
            'credits' => $t->credits,
            'balance' => $t->balance_after,
            'description' => $t->description,
            'reference' => $t->reference,
            'property_id' => $t->property_id,
            'segment' => $t->property?->segment,
            'property_type' => $t->property_type,
            'category' => $t->category,
            'location' => $t->location,
            'total' => $t->total !== null ? (float) $t->total : null,
            'expires_at' => $t->credits > 0 ? $t->expires_at?->toIso8601String() : null,
        ];
    }
}
