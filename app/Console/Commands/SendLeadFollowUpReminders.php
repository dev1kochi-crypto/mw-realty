<?php

namespace App\Console\Commands;

use App\Services\Crm\LeadFollowUpService;
use Illuminate\Console\Command;

/** Every few minutes: bell + email for lead follow-ups that are due (see LeadFollowUpService::sendDueReminders). */
class SendLeadFollowUpReminders extends Command
{
    protected $signature = 'leads:send-follow-up-reminders';

    protected $description = 'Remind agents / agencies about lead follow-ups that are due';

    public function handle(LeadFollowUpService $followUps): int
    {
        $this->info('Sent ' . $followUps->sendDueReminders() . ' follow-up reminder(s).');

        return self::SUCCESS;
    }
}
