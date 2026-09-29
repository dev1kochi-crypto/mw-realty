<?php

namespace Database\Seeders;

use App\Models\PortalUser;
use Illuminate\Database\Seeder;

/**
 * Local demo: TOTAL residential listings for ONE agency — (TOTAL - UNASSIGNED) assigned round-robin
 * to its active agents, UNASSIGNED kept on the agency with no agent. Built by
 * DemoFilterCoveragePropertiesSeeder (photos, details, floor plans, varied types / prices / areas);
 * idempotent, so a re-run adds nothing.
 *
 * Agency: DEMO_AGENCY_ID from the environment, else "Mighty warners".
 *
 *   php artisan db:seed --class=DemoAgencyListingsSeeder
 */
class DemoAgencyListingsSeeder extends Seeder
{
    private const TOTAL = 40;
    private const UNASSIGNED = 10;

    public function run(): void
    {
        $agency = env('DEMO_AGENCY_ID')
            ? PortalUser::where('type', 'company')->findOrFail(env('DEMO_AGENCY_ID'))
            : PortalUser::where('type', 'company')->where('company_name', 'Mighty warners')->firstOrFail();

        $created = app(DemoFilterCoveragePropertiesSeeder::class)->seedAgencyListings($agency, self::TOTAL, self::UNASSIGNED);

        $withAgent = $agency->properties()->whereNotNull('agent_id')->count();
        $this->command?->info("{$agency->displayName()}: {$created} listing(s) created — {$withAgent} with an agent, "
            . $agency->properties()->whereNull('agent_id')->count() . ' without.');
    }
}
