<?php

namespace App\Jobs;

use App\Models\PropertyFinderConnection;
use App\Services\PropertyFinder\PropertyFinderImporter;
use App\Services\PropertyFinder\PropertyFinderReview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Imports an account's Property Finder listings in the background (PropertyFinderImporter::sync),
 * with live progress (sync_status / sync_added / sync_skipped) on CRM › Integrations. When listings
 * were added, Super Admin gets a bell to review them.
 */
class SyncPropertyFinderListings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;
    public int $timeout = 3600;

    /** $mode: 'all' (every live listing) | 'new' (only listings added since the last sync). */
    public function __construct(public int $connectionId, public string $mode = 'all')
    {
    }

    /**
     * Mark it waiting and queue it on the "imports" queue (config/queue.php) — the page returns at
     * once and shows live progress while the imports worker (bootstrap/app.php schedule) runs it.
     */
    public static function start(PropertyFinderConnection $connection, string $mode): void
    {
        $connection->forceFill([
            'sync_status' => 'queued', 'sync_mode' => $mode, 'sync_added' => 0, 'sync_skipped' => 0, 'sync_failed' => 0,
            'sync_error' => null, 'sync_finished_at' => null,
        ])->save();
        static::dispatch($connection->id, $mode)->onConnection('imports')->onQueue('imports');
    }

    /** A sync that died (worker killed / timed out) — show it as failed instead of spinning. */
    public function failed(\Throwable $e): void
    {
        PropertyFinderConnection::whereKey($this->connectionId)->whereIn('sync_status', ['queued', 'running'])
            ->update(['sync_status' => 'failed', 'sync_error' => 'The sync stopped unexpectedly: ' . $e->getMessage(), 'sync_finished_at' => now()]);
    }

    public function handle(PropertyFinderImporter $importer): void
    {
        $connection = PropertyFinderConnection::with('owner')->find($this->connectionId);
        if (!$connection || !$connection->owner) {
            return;
        }
        // A second copy of the same sync (double click, retried job) leaves the running one alone.
        if ($connection->sync_status === 'running' && $connection->updated_at?->gt(now()->subMinutes(5))) {
            return;
        }
        @set_time_limit(0);
        $startedAt = now();
        $connection->forceFill(['sync_status' => 'running'])->save();

        try {
            [$added, $skipped, $failed] = $importer->sync($connection, $this->mode, function (int $added, int $skipped, int $failed) use ($connection) {
                $connection->forceFill(['sync_added' => $added, 'sync_skipped' => $skipped, 'sync_failed' => $failed]);
                // Touch the row now and then: the page polls it, and it proves the sync is alive.
                if (($added + $skipped + $failed) % 3 === 0) {
                    $connection->saveQuietly();
                }
            });

            $connection->forceFill([
                'sync_status' => 'done', 'sync_added' => $added, 'sync_skipped' => $skipped, 'sync_failed' => $failed,
                'sync_error' => $importer->stoppedBecause ?? ($failed ? "{$failed} listing" . ($failed === 1 ? '' : 's') . ' could not be imported — sync again to retry.' : null),
                'sync_finished_at' => now(), 'last_synced_at' => $startedAt,
            ] + ($this->mode === 'all' && !$importer->stoppedBecause ? ['last_full_sync_at' => $startedAt] : []))->save();

            if ($added) {
                PropertyFinderReview::notifyAdmins($connection->owner, $added);
            }
        } catch (\Throwable $e) {
            Log::warning("Property Finder sync for account {$connection->portal_user_id} failed: " . $e->getMessage());
            $connection->forceFill(['sync_status' => 'failed', 'sync_error' => $e->getMessage(), 'sync_finished_at' => now()])->save();
        }
    }
}
