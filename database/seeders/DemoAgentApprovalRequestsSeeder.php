<?php

namespace Database\Seeders;

use App\Models\AgencyAgent;
use App\Models\PortalUser;
use App\Services\Agency\AgencyMembershipService;
use Illuminate\Database\Seeder;

/**
 * Local demo: agents an agency ADDED that are waiting for Super Admin approval, so Clients ›
 * Agency Agents › Awaiting approval (Approve / Reject) and its sidebar count can be tried out.
 * Goes through AgencyMembershipService::createAgentForAgency() — exactly what the agency's
 * "Add agent" does (pending account + pending membership, admins notified). Tops the agency up
 * to MAX pending, never more; idempotent — agents are keyed by email.
 *
 * Agency: DEMO_AGENCY_ID from the environment, else "Mighty warners", else the first approved agency.
 *
 *   php artisan db:seed --class=DemoAgentApprovalRequestsSeeder
 */
class DemoAgentApprovalRequestsSeeder extends Seeder
{
    private const MAX = 5;

    private const NAMES = ['Zara Sheikh', 'Mateo Rossi', 'Huda Al Zaabi', 'Kiran Thomas', 'Isabella Cruz'];

    public function run(): void
    {
        if (!app()->environment('local')) {
            throw new \RuntimeException('DemoAgentApprovalRequestsSeeder is restricted to the local environment.');
        }

        $agencies = PortalUser::where('type', 'company')->where('status', 'approved')->where('is_active', true);
        $agency = env('DEMO_AGENCY_ID')
            ? (clone $agencies)->findOrFail(env('DEMO_AGENCY_ID'))
            : ((clone $agencies)->where('company_name', 'Mighty warners')->first() ?? (clone $agencies)->orderBy('id')->firstOrFail());

        $pending = AgencyAgent::where('agency_id', $agency->id)->where('status', AgencyAgent::PENDING)->count();
        $service = app(AgencyMembershipService::class);
        $added = 0;

        foreach (self::NAMES as $i => $name) {
            if ($pending + $added >= self::MAX) {
                break;
            }
            $email = 'demo-added-' . $agency->id . '-' . ($i + 1) . '@example.test';
            if (PortalUser::where('email', $email)->exists()) {
                continue; // already created on an earlier run
            }

            $service->createAgentForAgency($agency, [
                'name' => $name,
                'email' => $email,
                'phone' => '+97156' . str_pad((string) ($agency->id * 10 + $i + 1), 7, '0', STR_PAD_LEFT),
                'brn_number' => 'BRN' . (80000 + $agency->id * 10 + $i),
            ]);
            $added++;
        }

        $this->command?->info("{$agency->displayName()}: {$added} agent(s) added for approval — " . ($pending + $added) . ' awaiting approval (max ' . self::MAX . ').');
    }
}
