<?php

namespace App\Console\Commands;

use App\Services\FeaturedListingService;
use Illuminate\Console\Command;

class ExpireFeaturedProperties extends Command
{
    protected $signature = 'properties:expire-featured';

    protected $description = 'Start scheduled featured listings and un-feature the ones whose featured period has ended';

    public function handle(FeaturedListingService $featured): int
    {
        $count = $featured->sync();
        $this->info("Updated {$count} featured listing(s).");

        return self::SUCCESS;
    }
}
