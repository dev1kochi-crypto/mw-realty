<?php

namespace App\Console\Commands;

use App\Services\AdBookingService;
use Illuminate\Console\Command;

class SettleAds extends Command
{
    protected $signature = 'ads:settle';

    protected $description = 'Close client ads whose booked time ended while still waiting for review, and refund them';

    public function handle(AdBookingService $ads): int
    {
        $count = $ads->settle();
        $this->info("Closed {$count} unreviewed ad(s).");

        return self::SUCCESS;
    }
}
