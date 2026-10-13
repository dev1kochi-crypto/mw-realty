<?php

namespace App\Http\Controllers\CmsKit;

use App\Models\CreditRate;
use App\Models\CreditSetting;
use App\Models\CreditTransaction;
use App\Models\Filter;
use App\Models\PortalUser;
use App\Services\CreditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin › Credits — turning credits on/off, credit pricing (price per credit, VAT, validity, top-up limits),
 * what each add-on costs (the rates the CRM calculator and checkout use) and granting credits to an account,
 * under credits.*. Admin › Credit History — every account's credit ledger, under credit-history.*.
 * See App\Services\CreditService.
 */
class CreditController extends Controller
{
    public const TYPES = [
        CreditTransaction::TOPUP => 'Bought',
        CreditTransaction::GRANT => 'Given by admin',
        CreditTransaction::SPEND => 'Spent',
        CreditTransaction::REFUND => 'Refunded',
        CreditTransaction::EXPIRE => 'Expired',
    ];

    private const FILTERS = ['search', 'type', 'kind', 'account_type', 'from', 'to'];

    public function index()
    {
        $label = fn (string $key) => (Filter::where('key', $key)->with('activeValues')->first()?->activeValues ?? collect())
            ->mapWithKeys(fn ($v) => [(string) $v->value => $v->getTranslation('label') ?: Str::headline((string) $v->value)]);
        $thisMonth = fn () => CreditTransaction::where('created_at', '>=', now()->startOfMonth());

        return view('cms-kit::credits.index', [
            'settings' => CreditSetting::current(),
            'rates' => CreditRate::orderBy('order_index')->orderBy('id')->get(),
            'listingTypes' => $label('listing_type'),
            'propertyTypes' => $label('property_type'),
            'sold' => (float) CreditTransaction::where('type', CreditTransaction::TOPUP)->sum('total'),
            'outstanding' => (int) CreditTransaction::spendable()->sum('remaining'),
            'inThisMonth' => (int) $thisMonth()->whereIn('type', [CreditTransaction::TOPUP, CreditTransaction::GRANT])->sum('credits'),
            'spentThisMonth' => (int) -$thisMonth()->where('type', CreditTransaction::SPEND)->sum('credits'),
        ]);
    }

    /** Admin › Credit History — every account's credit ledger, filterable, with totals for the current filter. */
    public function history(Request $request)
    {
        $query = $this->filtered($request);

        $totals = (clone $query)->toBase()
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(CASE WHEN credits > 0 THEN credits END), 0) as credits_in, COALESCE(-SUM(CASE WHEN credits < 0 THEN credits END), 0) as credits_out, COALESCE(SUM(CASE WHEN type = ? THEN total END), 0) as money, COUNT(DISTINCT account_id) as accounts', [CreditTransaction::TOPUP])
            ->first();

        return view('cms-kit::credits.history', [
            'transactions' => $query->with('account:id,type,name,company_name,email')
                ->orderByDesc('created_at')->orderByDesc('id')
                ->paginate(25)->withQueryString(),
            'totals' => $totals,
            'types' => self::TYPES,
            'filtersActive' => collect($request->only(self::FILTERS))->filter(fn ($v) => filled($v))->isNotEmpty(),
        ]);
    }

    /** CSV of the same rows the history page shows for the current filter. */
    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered($request)->with('account:id,type,name,company_name,email')->orderByDesc('created_at')->orderByDesc('id');

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Account', 'Type', 'Email', 'What happened', 'Description', 'Done by', 'Credits', 'Balance after', 'Listing', 'Paid (AED)']);
            $rows->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $t) {
                    fputcsv($out, [
                        $t->created_at?->format('Y-m-d H:i'),
                        $t->account?->displayName() ?? 'Deleted account',
                        $t->account ? ucfirst($t->account->type) : '',
                        $t->account?->email,
                        self::TYPES[$t->type] ?? ucfirst($t->type),
                        $t->description,
                        $t->actor_name ?: 'System',
                        $t->credits,
                        $t->balance_after,
                        $t->reference,
                        $t->total !== null ? number_format((float) $t->total, 2, '.', '') : '',
                    ]);
                }
            });
            fclose($out);
        }, 'credit-history-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    private function filtered(Request $request): Builder
    {
        $search = trim((string) $request->input('search', ''));

        return CreditTransaction::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('reference', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('account', fn ($a) => $a->where('email', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")->orWhere('company_name', 'like', "%{$search}%"))))
            ->when(array_key_exists((string) $request->input('type'), self::TYPES), fn ($q) => $q->where('type', $request->input('type')))
            ->when(in_array($request->input('kind'), ['listing', 'premium'], true), fn ($q) => $q->where('kind', $request->input('kind')))
            ->when(in_array($request->input('account_type'), ['agent', 'company'], true), fn ($q) => $q->whereHas('account', fn ($a) => $a->where('type', $request->input('account_type'))))
            ->when($request->date('from'), fn ($q, $from) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($request->date('to'), fn ($q, $to) => $q->where('created_at', '<=', $to->endOfDay()));
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'price_per_credit' => 'required|numeric|min:0.01|max:100000',
            'vat_percent' => 'required|numeric|min:0|max:100',
            'validity_days' => 'required|integer|min:1|max:3650',
            'min_topup' => 'required|integer|min:1',
            'max_topup' => 'required|integer|gte:min_topup',
        ]);
        CreditSetting::current()->update($data);

        return back()->with('success', 'Credit settings saved.');
    }

    /** The on/off switch — when off, agents can't buy or spend credits and the CRM shows nothing about them. */
    public function toggle(Request $request)
    {
        $enabled = $request->boolean('enabled');
        CreditSetting::current()->update(['enabled' => $enabled]);

        return back()->with('success', $enabled ? 'Credits are ON — agents can buy and spend credits in the CRM.' : 'Credits are OFF — nobody can buy credits; agents can still use the credits they have.');
    }

    /** Replaces the whole rate table with the rows posted (rows left blank are dropped). */
    public function updateRates(Request $request)
    {
        $request->validate([
            'rates' => 'array',
            'rates.*.segment' => 'nullable|in:residential,commercial',
            'rates.*.listing_type' => 'nullable|string|max:20',
            'rates.*.property_type' => 'nullable|string|max:50',
            'rates.*.min_price' => 'nullable|numeric|min:0',
            'rates.*.max_price' => 'nullable|numeric|min:0',
            'rates.*.listing' => 'required|integer|min:0',
            'rates.*.premium_7' => 'required|integer|min:0',
            'rates.*.premium_15' => 'required|integer|min:0',
            'rates.*.premium_30' => 'required|integer|min:0',
        ]);

        $rows = collect($request->input('rates', []))->values();
        CreditRate::query()->delete();
        $rows->each(fn ($row, $i) => CreditRate::create([
            'segment' => $row['segment'] ?: null,
            'listing_type' => $row['listing_type'] ?: null,
            'property_type' => $row['property_type'] ?: null,
            'min_price' => ($row['min_price'] ?? '') === '' ? null : $row['min_price'],
            'max_price' => ($row['max_price'] ?? '') === '' ? null : $row['max_price'],
            'listing' => (int) $row['listing'],
            'premium_7' => (int) $row['premium_7'],
            'premium_15' => (int) $row['premium_15'],
            'premium_30' => (int) $row['premium_30'],
            'status' => true,
            'order_index' => $i,
        ]));

        return back()->with('success', 'Credit rates saved.' . ($rows->contains(fn ($r) => !$r['segment'] && !$r['listing_type'] && !$r['property_type'])
            ? '' : ' Tip: add a row with every filter empty so any listing has a price.'));
    }

    /** Who an email belongs to and their balance, so the "Give credits manually" form shows who gets them. */
    public function lookup(Request $request, CreditService $credits)
    {
        $user = PortalUser::where('email', trim((string) $request->input('email')))->first();
        if (!$user) {
            return response()->json(['found' => false]);
        }
        $wallet = $credits->wallet($user);

        return response()->json([
            'found' => true,
            'name' => $user->displayName(),
            'type' => ucfirst((string) $user->type),
            'wallet' => $wallet->id !== $user->id ? $wallet->displayName() : null,
            'balance' => $credits->balance($wallet),
        ]);
    }

    /** Adds credits to an account (goodwill, bank transfer, correction) — they expire like a top-up. */
    public function grant(Request $request, CreditService $credits)
    {
        $data = $request->validate([
            'email' => 'required|email|exists:portal_users,email',
            'credits' => 'required|integer|min:1|max:1000000',
            'note' => 'nullable|string|max:120',
        ]);
        $account = $credits->wallet(PortalUser::where('email', $data['email'])->firstOrFail());
        $credits->credit($account, (int) $data['credits'], CreditTransaction::GRANT, $data['note'] ?: 'Credits added by MW Realty', 'MW Realty');

        return back()->with('success', number_format($data['credits']) . ' credits added to ' . $account->displayName() . '.');
    }
}
