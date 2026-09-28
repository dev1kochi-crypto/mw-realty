<?php

namespace App\Http\Controllers\Api;

use App\Models\Currency;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

/**
 * GET /api/currencies — the header currency switcher's options. Prices everywhere else stay in
 * AED; the frontend multiplies by `rate` (units per 1 AED) for display.
 */
class CurrencyController extends Controller
{
    public function index()
    {
        $currencies = Cache::remember(Currency::CACHE_KEY, 600, fn () => Currency::active()
            ->orderByDesc('is_default')->orderBy('order_index')->orderBy('code')
            ->get(['code', 'name', 'symbol', 'rate', 'is_default'])
            ->map(fn (Currency $c) => [
                'code' => $c->code,
                'name' => $c->name,
                'symbol' => $c->symbol ?: $c->code,
                'rate' => $c->isBase() ? 1.0 : $c->rate,
                'is_default' => $c->is_default,
            ])->values()->all());

        return response()->json(['base' => Currency::BASE, 'currencies' => $currencies]);
    }
}
