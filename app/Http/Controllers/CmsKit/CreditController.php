<?php

namespace App\Http\Controllers\CmsKit;

use App\Models\CreditRate;
use App\Models\CreditSetting;
use App\Models\CreditTransaction;
use App\Models\Filter;
use App\Models\PortalUser;
use App\Services\CreditService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

/**
 * Admin › Credits — credit pricing (price per credit, VAT, validity, top-up limits), what each add-on
 * costs (the rates the CRM calculator and checkout use), granting credits to an account, and every
 * account's credit ledger. Under the plans.* permissions. See App\Services\CreditService.
 */
class CreditController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('q', ''));

        $transactions = CreditTransaction::with('account:id,type,name,company_name,email')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('reference', 'like', "%{$search}%")
                ->orWhereHas('account', fn ($a) => $a->where('email', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")->orWhere('company_name', 'like', "%{$search}%"))))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        $label = fn (string $key) => (Filter::where('key', $key)->with('activeValues')->first()?->activeValues ?? collect())
            ->mapWithKeys(fn ($v) => [(string) $v->value => $v->getTranslation('label') ?: Str::headline((string) $v->value)]);

        return view('cms-kit::credits.index', [
            'settings' => CreditSetting::current(),
            'rates' => CreditRate::orderBy('order_index')->orderBy('id')->get(),
            'transactions' => $transactions,
            'search' => $search,
            'listingTypes' => $label('listing_type'),
            'propertyTypes' => $label('property_type'),
            'sold' => (float) CreditTransaction::where('type', CreditTransaction::TOPUP)->sum('total'),
            'outstanding' => (int) CreditTransaction::spendable()->sum('remaining'),
        ]);
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
        CreditSetting::current()->update($data + ['enabled' => $request->boolean('enabled')]);

        return back()->with('success', 'Credit settings saved.');
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
