<?php

namespace App\Jobs;

use App\Models\FacebookPageConnection;
use App\Services\Integrations\FacebookConnectionHealth;
use App\Services\Integrations\FacebookLeadAds;
use App\Services\Integrations\FacebookLeadImporter;
use App\Services\Integrations\FacebookTokenException;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Imports a Facebook Page's leads created since $since, in the background (FacebookLeadImporter::sync):
 * the Page's existing leads when it is connected, and the leads missed while it needed reconnecting.
 * Progress (import_status / import_added / import_skipped) is shown live on CRM › Integrations.
 * Duplicates never reach the CRM twice — see FacebookLeadImporter::import.
 */
class ImportFacebookPageLeads implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;
    public int $timeout = 1800;

    public function __construct(public int $connectionId, public int $since, public bool $notify = false)
    {
    }

    /** Mark the Page's import as waiting and queue it. */
    public static function start(FacebookPageConnection $connection, \DateTimeInterface $since, bool $notify = false): void
    {
        $connection->forceFill(['import_status' => 'queued', 'import_added' => 0, 'import_skipped' => 0, 'import_error' => null, 'import_finished_at' => null])->save();
        static::dispatch($connection->id, $since->getTimestamp(), $notify)->afterCommit();
    }

    public function handle(FacebookLeadImporter $importer, FacebookConnectionHealth $health, FacebookLeadAds $facebook): void
    {
        $connection = FacebookPageConnection::find($this->connectionId);
        if (!$connection) {
            return;
        }
        $connection->forceFill(['import_status' => 'running'])->save();

        try {
            // Nothing to fetch? Say so straight away instead of a spinner.
            if (!$facebook->forms($connection->page_id, $connection->page_access_token)) {
                $connection->forceFill(['import_status' => 'empty', 'import_error' => 'This Page has no lead forms yet — there are no leads to import.', 'import_finished_at' => now()])->save();

                return;
            }

            $importer->sync($connection, Carbon::createFromTimestamp($this->since), $this->notify, function (int $added, int $skipped) use ($connection) {
                // Every few leads is enough for the progress shown on the page.
                if (($added + $skipped) % 5 === 0) {
                    $connection->forceFill(['import_added' => $added, 'import_skipped' => $skipped])->saveQuietly();
                }
                $connection->import_added = $added;
                $connection->import_skipped = $skipped;
            });
            $found = $connection->import_added + $connection->import_skipped;
            $connection->forceFill($found
                ? ['import_status' => 'done', 'import_finished_at' => now()]
                : ['import_status' => 'empty', 'import_error' => 'No leads on Facebook for this Page since ' . Carbon::createFromTimestamp($this->since)->format('d M Y') . '.', 'import_finished_at' => now()]
            )->save();
        } catch (FacebookTokenException $e) {
            $health->tokenFailed($connection, $e);
            $connection->forceFill(['import_status' => 'failed', 'import_error' => $e->reason(), 'import_finished_at' => now()])->save();
        } catch (\Throwable $e) {
            Log::warning("Facebook lead import for page {$connection->page_id} failed: " . $e->getMessage());
            $connection->forceFill(['import_status' => 'failed', 'import_error' => $e->getMessage(), 'import_finished_at' => now()])->save();
        }
    }
}
