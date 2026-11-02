<?php

namespace App\Console\Commands;

use App\Models\Currency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Refreshes every active currency's rate (units per 1 AED) from config('services.exchange_rates.url')
 * — an AED-based feed shaped { result: "success", rates: { USD: 0.2723, … } }. Scheduled hourly
 * when CURRENCY_RATES_AUTO_SYNC=true; can always be run by hand. A currency the feed doesn't
 * list keeps its current rate.
 */
class SyncCurrencyRates extends Command
{
    protected $signature = 'currencies:sync-rates';

    protected $description = 'Update the website currency rates (per 1 AED) from the live exchange-rate feed';

    public function handle(): int
    {
        try {
            $response = Http::timeout(15)->get(config('services.exchange_rates.url'));
        } catch (\Throwable $e) {
            $this->error('Rate feed unreachable: ' . $e->getMessage());

            return self::FAILURE;
        }

        $rates = $response->json('rates');
        if (!$response->successful() || $response->json('result', 'success') !== 'success' || !is_array($rates)) {
            $this->error('Rate feed returned an unexpected response (HTTP ' . $response->status() . ').');

            return self::FAILURE;
        }

        $updated = 0;
        foreach (Currency::active()->where('code', '!=', Currency::BASE)->get() as $currency) {
            $rate = $rates[$currency->code] ?? null;
            if (is_numeric($rate) && $rate > 0) {
                $currency->rate = $rate;
                $currency->updated_at = now(); // "Rates as of" moves even when the rate didn't
                $currency->save();
                $updated++;
            } else {
                $this->warn("{$currency->code}: not in the feed, rate kept.");
            }
        }

        $this->info("Updated {$updated} currency rate(s).");

        return self::SUCCESS;
    }
}
