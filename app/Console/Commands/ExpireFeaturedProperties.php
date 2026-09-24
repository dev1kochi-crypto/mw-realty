<?php

namespace App\Console\Commands;

use App\Services\FeaturedListingService;
use Illuminate\Console\Command;

class ExpireFeaturedProperties extends Command
{
    protected $signature = 'properties:expire-featured';

    protected $description = 'Un-feature listings whose plan-based featured period has ended';

    public function handle(FeaturedListingService $featured): int
    {
        $count = $featured->expire();
        $this->info("Expired {$count} featured listing(s).");

        return self::SUCCESS;
    }
}
