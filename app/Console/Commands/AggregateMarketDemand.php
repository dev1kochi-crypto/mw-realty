<?php

namespace App\Console\Commands;

use App\Services\MarketDemand\DemandAggregator;
use Illuminate\Console\Command;

/**
 * Rolls website activity into the anonymous demand tables (see DemandAggregator). Scheduled hourly
 * for today and yesterday. Run with --days=30 once after deploying to backfill from the events
 * that still exist (anonymous ones are only kept 30 days).
 */
class AggregateMarketDemand extends Command
{
    protected $signature = 'market-demand:aggregate
        {--days=2 : How many days back to (re)build, today included}
        {--date= : Build one specific day (Y-m-d) instead}';

    protected $description = 'Aggregate searches, views, favourites and enquiries into anonymous market demand figures';

    public function handle(DemandAggregator $aggregator): int
    {
        $dates = $this->option('date')
            ? [\Illuminate\Support\Carbon::parse($this->option('date'))]
            : collect(range(0, max(1, (int) $this->option('days')) - 1))->map(fn ($d) => today()->subDays($d))->all();

        foreach ($dates as $date) {
            $result = $aggregator->aggregate($date);
            $this->line("{$date->toDateString()}: {$result['events']} events → {$result['rows']} demand rows, {$result['amenity_rows']} amenity rows");
        }

        // Fresh numbers for the listing pages.
        \Illuminate\Support\Facades\Cache::forever('market-demand:version', now()->timestamp);

        return self::SUCCESS;
    }
}
