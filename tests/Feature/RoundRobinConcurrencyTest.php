<?php

namespace Tests\Feature;

use App\Models\AgencyAgent;
use App\Models\AgencyLeadAssignmentSetting;
use App\Models\Lead;
use App\Models\Plan;
use App\Models\PortalUser;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Feature\Concerns\BuildsAgencies;
use Tests\TestCase;

/**
 * Round-robin under real concurrency: several separate PHP processes capture a lead for the
 * same agency at the same instant. Fixtures are committed (no wrapping test transaction) so the
 * child processes can see them, and removed again in tearDown. Requires MySQL — SQLite has no
 * row locks, so this test skips itself there.
 */
class RoundRobinConcurrencyTest extends TestCase
{
    use BuildsAgencies;

    private const PROCESSES = 9;

    private array $cleanup = ['portal_users' => [], 'plans' => [], 'properties' => []];

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Needs MySQL row locking.');
        }
        if (!RefreshDatabaseState::$migrated) {
            $this->artisan('migrate:fresh');
            RefreshDatabaseState::$migrated = true;
        }
    }

    public function test_simultaneous_enquiries_are_spread_evenly_without_duplicates(): void
    {
        $plan = $this->plan();
        $agency = $this->agency([], $plan);
        $agents = [$this->memberAgent($agency), $this->memberAgent($agency), $this->memberAgent($agency)];
        $property = $this->property($agency);
        $this->cleanup = [
            'plans' => PortalUser::whereIn('id', [$agency->id, ...array_map(fn ($a) => $a->id, $agents)])->pluck('plan_id')->push($plan->id)->unique()->all(),
            'portal_users' => [$agency->id, ...array_map(fn ($a) => $a->id, $agents)],
            'properties' => [$property->id],
        ];

        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'),
            'DB_DATABASE' => config('database.connections.mysql.database'),
            'DB_USERNAME' => config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
            'CACHE_STORE' => 'array',
        ];
        $startAt = microtime(true) + 6; // enough for every process to boot and wait at the barrier

        $processes = collect(range(1, self::PROCESSES))->map(function () use ($property, $startAt, $env) {
            $process = new Process([PHP_BINARY, base_path('tests/Support/capture_lead.php'), (string) $property->id, (string) $startAt], base_path(), $env, null, 60);
            $process->start();

            return $process;
        });
        $processes->each(fn (Process $p) => $p->wait());

        $processes->each(fn (Process $p) => $this->assertTrue($p->isSuccessful(), $p->getErrorOutput() . $p->getOutput()));

        $perAgent = Lead::where('property_id', $property->id)->selectRaw('agent_id, count(*) as total')->groupBy('agent_id')->pluck('total', 'agent_id');

        $this->assertSame(self::PROCESSES, (int) $perAgent->sum());
        foreach ($agents as $agent) {
            $this->assertSame(self::PROCESSES / 3, (int) ($perAgent[$agent->id] ?? 0), "Agent {$agent->id} should get exactly its share: " . $perAgent->toJson());
        }
    }

    protected function tearDown(): void
    {
        if (DB::getDriverName() === 'mysql' && $this->cleanup['portal_users']) {
            $leadIds = Lead::withTrashed()->whereIn('property_id', $this->cleanup['properties'])->pluck('id');
            DB::table('lead_assignment_history')->whereIn('lead_id', $leadIds)->delete();
            Lead::withTrashed()->whereIn('id', $leadIds)->forceDelete();
            Property::whereIn('id', $this->cleanup['properties'])->delete();
            AgencyLeadAssignmentSetting::whereIn('agency_id', $this->cleanup['portal_users'])->delete();
            AgencyAgent::whereIn('agency_id', $this->cleanup['portal_users'])->delete();
            DB::table('notifications')->where('notifiable_type', PortalUser::class)->whereIn('notifiable_id', $this->cleanup['portal_users'])->delete();
            PortalUser::whereIn('id', $this->cleanup['portal_users'])->update(['company_id' => null]);
            PortalUser::whereIn('id', $this->cleanup['portal_users'])->delete();
            Plan::whereIn('id', $this->cleanup['plans'])->delete();
        }

        parent::tearDown();
    }
}
