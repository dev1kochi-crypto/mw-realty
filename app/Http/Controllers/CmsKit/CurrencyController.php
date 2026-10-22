<?php

namespace App\Http\Controllers\CmsKit;

use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin › General Settings › Currencies — the website's currency switcher options and their
 * exchange rates. Listing prices are always entered and stored in AED (the base currency); these
 * rates only change how prices are displayed.
 */
class CurrencyController extends Controller
{
    public function index()
    {
        return view('cms-kit::currencies.index', [
            'currencies' => Currency::orderByDesc('is_default')->orderBy('order_index')->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['order_index'] = (int) Currency::max('order_index') + 1;
        Currency::create($data);

        return redirect()->route('cms.currencies.index')->with('success', 'Currency added.');
    }

    public function update(Request $request, $id)
    {
        $currency = Currency::findOrFail($id);
        $data = $this->validated($request, $currency);
        // AED is the base every price is stored in — its code and rate are fixed.
        if ($currency->isBase()) {
            unset($data['code']);
            $data['rate'] = 1;
        }
        $currency->update($data);

        return redirect()->route('cms.currencies.index')->with('success', 'Currency updated.');
    }

    public function toggleStatus($id)
    {
        $currency = Currency::findOrFail($id);
        if ($currency->is_default && $currency->status) {
            return back()->with('error', 'The default currency cannot be deactivated. Make another currency the default first.');
        }
        $currency->update(['status' => !$currency->status]);

        return back()->with('success', 'Currency ' . ($currency->status ? 'activated.' : 'deactivated.'));
    }

    /** The currency visitors see first (until they pick another one). */
    public function setDefault($id)
    {
        $currency = Currency::findOrFail($id);
        DB::transaction(function () use ($currency) {
            Currency::where('id', '!=', $currency->id)->update(['is_default' => false]);
            $currency->update(['is_default' => true, 'status' => true]);
        });

        return back()->with('success', "{$currency->code} is now the default currency.");
    }

    public function destroy($id)
    {
        $currency = Currency::findOrFail($id);
        if ($currency->isBase() || $currency->is_default) {
            return back()->with('error', $currency->isBase()
                ? 'AED is the base currency (all prices are stored in it) and cannot be deleted.'
                : 'The default currency cannot be deleted. Make another currency the default first.');
        }
        $currency->delete();

        return back()->with('success', 'Currency deleted.');
    }

    private function validated(Request $request, ?Currency $currency = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code', $currency?->code)))]);

        return $request->validate([
            'code' => ['required', 'alpha', 'size:3', Rule::unique('currencies', 'code')->ignore($currency?->id)],
            'name' => ['required', 'string', 'max:60'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'rate' => [$currency?->isBase() ? 'nullable' : 'required', 'numeric', 'gt:0', 'max:1000000'],
        ], [
            'rate.gt' => 'The rate must be more than 0.',
        ]);
    }
}
