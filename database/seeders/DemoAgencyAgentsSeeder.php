<?php

namespace Database\Seeders;

use App\Models\AgencyAgent;
use App\Models\PortalUser;
use App\Models\Property;
use App\Services\Agency\AgencyMembershipService;
use App\Services\Agency\AssignmentActor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Local demo agents: tops every approved agency up to AGENTS_PER_AGENCY approved agents, then
 * spreads that agency's demo listings (DemoFilterCoveragePropertiesSeeder, slug "demo-{agencyId}-…")
 * round-robin over its agents, leaving every 4th one on the agency itself.
 *
 * Agents are created approved + active directly (not via AgencyMembershipService::createAgentForAgency,
 * which makes them pending and emails the admins). Idempotent — keyed by email.
 *
 *   php artisan db:seed --class=DemoAgencyAgentsSeeder
 */
class DemoAgencyAgentsSeeder extends Seeder
{
    private const AGENTS_PER_AGENCY = 3;

    private const NAMES = [
        'Omar Al Suwaidi', 'Priya Nair', 'Daniel Brooks', 'Fatima Al Hashimi', 'Rahul Menon', 'Sofia Petrova',
        'Khalid Al Marri', 'Aisha Rahman', 'Lucas Fernandes', 'Mariam Haddad', 'Arjun Pillai', 'Emma Clarke',
        'Yousef Al Nuaimi', 'Neha Kapoor', 'Hassan Qureshi', 'Layla Mansour', 'Vikram Rao', 'Chloe Martin',
        'Saeed Al Ketbi', 'Anjali Das', 'Tariq Farouk', 'Nadia Karim', 'Rohan Iyer', 'Olivia Bennett',
        'Hamdan Al Falasi', 'Zara Sheikh', 'Mateo Rossi', 'Huda Al Zaabi', 'Kiran Thomas', 'Isabella Cruz',
        'Faisal Al Amiri', 'Meera Joseph', 'Adam Novak', 'Reem Al Shamsi', 'Sanjay Varma', 'Grace Wilson',
    ];

    private const AREAS = [
        ['Downtown Dubai', 'Business Bay', 'Dubai Marina'],
        ['Palm Jumeirah', 'Dubai Hills Estate', 'Arabian Ranches'],
        ['Jumeirah Village Circle', 'Al Furjan', 'Jumeirah Lake Towers'],
        ['Dubai Marina', 'Abu Dhabi', 'Sharjah'],
        ['Business Bay', 'Ajman', 'Ras Al Khaimah'],
    ];

    private const BADGES = [['Responsive Broker'], ['Top Performer'], ['Verified Agent'], ['Responsive Broker', 'Top Performer'], []];
    private const LANGUAGES = ['English, Arabic', 'English, Hindi', 'English, Russian', 'English, Urdu', 'English, French', 'English, Malayalam'];

    public function run(): void
    {
        if (!app()->environment('local')) {
            throw new \RuntimeException('DemoAgencyAgentsSeeder is restricted to the local environment.');
        }

        $membership = app(AgencyMembershipService::class);
        $nameIndex = 0;
        $createdAgents = 0;
        $reassigned = 0;

        $agencies = PortalUser::where('type', 'company')->where('status', 'approved')->orderBy('id')->get();
        foreach ($agencies as $agency) {
            DB::transaction(function () use ($agency, $membership, &$nameIndex, &$createdAgents, &$reassigned) {
                // 1) Top up to AGENTS_PER_AGENCY approved agents.
                $current = AgencyAgent::where('agency_id', $agency->id)->where('status', AgencyAgent::APPROVED)->count();
                for ($n = 1; $current < self::AGENTS_PER_AGENCY; $n++) {
                    $email = "demo-agent-{$agency->id}-{$n}@example.test";
                    $name = self::NAMES[$nameIndex++ % count(self::NAMES)];
                    $agent = PortalUser::where('email', $email)->first();
                    if (!$agent) {
                        $agent = $this->createAgent($agency, $email, $name, $n);
                        $createdAgents++;
                    }
                    $membershipRow = AgencyAgent::firstOrCreate(
                        ['agency_id' => $agency->id, 'agent_id' => $agent->id],
                        [
                            'status' => AgencyAgent::APPROVED,
                            'initiated_by' => AssignmentActor::AGENCY,
                            'account_created_by_agency' => true,
                            'invited_at' => now(),
                            'responded_at' => now(),
                            'approved_at' => now(),
                            'joined_at' => now(),
                        ]
                    );
                    if ($membershipRow->status === AgencyAgent::APPROVED) {
                        $current++;
                    }
                }

                // 2) Spread this agency's demo listings over its agents (every 4th stays unassigned).
                $agentIds = AgencyAgent::where('agency_id', $agency->id)->where('status', AgencyAgent::APPROVED)
                    ->orderBy('agent_id')->pluck('agent_id')->all();
                $listings = Property::where('portal_user_id', $agency->id)
                    ->where('slug', 'like', "demo-{$agency->id}-%")
                    ->orderBy('id')->get(['id', 'agent_id', 'portal_user_id']);

                foreach ($listings as $i => $property) {
                    $target = ($i % 4 === 3 || !$agentIds) ? null : $agentIds[$i % count($agentIds)];
                    if ((string) $property->agent_id === (string) $target) {
                        continue;
                    }
                    $from = $property->agent_id;
                    $property->update(['agent_id' => $target]);
                    $membership->recordPropertyChange(
                        $property,
                        match (true) { $from === null => 'agent_assigned', $target === null => 'agent_unassigned', default => 'agent_changed' },
                        $agency->id, $agency->id, $from, $target, AssignmentActor::system(), 'Local demo agent assignment.',
                    );
                    $reassigned++;
                }
            });
        }

        $this->command?->info("Demo agents created: {$createdAgents}; listings (re)assigned: {$reassigned}.");
    }

    private function createAgent(PortalUser $agency, string $email, string $name, int $n): PortalUser
    {
        $seed = $agency->id * 10 + $n;
        $years = 2 + ($seed % 14);
        $areas = self::AREAS[$seed % count(self::AREAS)];
        $agencyName = $agency->displayName();
        $phone = '+9715' . str_pad((string) (60000000 + $seed), 8, '0', STR_PAD_LEFT);

        return PortalUser::create([
            'type' => 'agent',
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'whatsapp_number' => $phone,
            'company_id' => $agency->id,
            'years_of_experience' => $years,
            'preferred_areas' => $areas,
            'badges' => self::BADGES[$seed % count(self::BADGES)],
            'translations' => [
                'bio' => [
                    'en' => "{$name} is a real estate consultant at {$agencyName} with {$years} years of experience helping clients buy, sell and rent across "
                        . implode(', ', $areas) . '. Speaks ' . self::LANGUAGES[$seed % count(self::LANGUAGES)] . '.',
                ],
            ],
            'brn_number' => (string) (40000 + $seed),
            'password' => Hash::make(Str::random(40)),
            'status' => 'approved',
            'status_changed_at' => now(),
            'kyc_review_status' => 'approved',
            'is_active' => true,
        ]);
    }
}
