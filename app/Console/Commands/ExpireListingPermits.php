<?php

namespace App\Console\Commands;

use App\Services\ListingComplianceService;
use Illuminate\Console\Command;

class ExpireListingPermits extends Command
{
    protected $signature = 'properties:expire-permits';

    protected $description = 'Take listings whose DLD advertising permit has expired off the website, and remind accounts of permits expiring soon';

    public function handle(ListingComplianceService $compliance): int
    {
        $expired = $compliance->expireDue();
        $reminded = $compliance->remindExpiring();
        $this->info("Unpublished {$expired} listing(s) with an expired permit; sent {$reminded} expiry reminder(s).");

        return self::SUCCESS;
    }
}
