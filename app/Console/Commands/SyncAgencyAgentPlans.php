<?php

namespace App\Console\Commands;

use App\Models\PortalUser;
use App\Services\Agency\AgencyMembershipService;
use Illuminate\Console\Command;

/**
 * One-off catch-up (safe to re-run): every agent currently connected through My Agency / Agents
 * (PortalUser::isOnAgencyPlan) is moved onto the agency's plan — own plan dropped to Free, a paid
 * Stripe subscription set not to renew (no refund), the dropped plan recorded on the membership.
 *
 *   php artisan agency:sync-agent-plans
 */
class SyncAgencyAgentPlans extends Command
{
    protected $signature = 'agency:sync-agent-plans';

    protected $description = "Move every connected agency agent onto their agency's plan";

    public function handle(AgencyMembershipService $memberships): int
    {
        $switched = 0;
        PortalUser::where('type', 'agent')->whereNotNull('company_id')->with(['currentMembership', 'company.plan'])
            ->chunkById(200, function ($agents) use ($memberships, &$switched) {
                foreach ($agents as $agent) {
                    if ($agent->isOnAgencyPlan() && !$agent->currentMembership->plan_switched_at) {
                        $memberships->moveOntoAgencyPlan($agent);
                        $this->line("  {$agent->name} → {$agent->company->displayName()}'s plan");
                        $switched++;
                    }
                }
            });

        $this->info("{$switched} agent(s) moved onto their agency's plan.");

        return self::SUCCESS;
    }
}
