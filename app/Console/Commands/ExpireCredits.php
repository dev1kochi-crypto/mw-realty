<?php

namespace App\Console\Commands;

use App\Services\CreditService;
use Illuminate\Console\Command;

class ExpireCredits extends Command
{
    protected $signature = 'credits:expire';

    protected $description = 'Write off credits whose validity period has ended (one "Expired credits" line per account)';

    public function handle(CreditService $credits): int
    {
        $count = $credits->expireDue();
        $this->info("Expired {$count} credit(s).");

        return self::SUCCESS;
    }
}
