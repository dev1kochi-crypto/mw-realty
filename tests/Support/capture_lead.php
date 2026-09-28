<?php

/**
 * Child process for RoundRobinConcurrencyTest: boots the app, waits for a shared start time so
 * every process hits the database together, then captures one lead exactly as
 * LeadCaptureController does (create + assign in one transaction). Prints the assigned agent id.
 *
 * Usage: php capture_lead.php <propertyId> <startAtUnixMicrotime>
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[$_, $propertyId, $startAt] = $argv;

$property = App\Models\Property::findOrFail((int) $propertyId);
Illuminate\Support\Facades\DB::connection()->getPdo(); // connect before the barrier

while (microtime(true) < (float) $startAt) {
    usleep(200);
}

try {
    $lead = Illuminate\Support\Facades\DB::transaction(fn () => app(App\Services\Agency\LeadAssignmentService::class)->assignNewLead(
    App\Models\Lead::create([
        'property_id' => $property->id,
        'portal_user_id' => $property->portal_user_id,
        'name' => 'Concurrent buyer',
        'message' => 'Simultaneous enquiry',
        'status' => 'active',
    ])
    ), App\Services\Agency\LeadAssignmentService::TRANSACTION_ATTEMPTS);
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e) . ': ' . $e->getMessage());
    exit(1);
}

echo $lead->agent_id;
