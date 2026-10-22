<?php

namespace App\Jobs;

use App\Models\FacebookLead;
use App\Models\FacebookPageConnection;
use App\Services\Integrations\FacebookConnectionHealth;
use App\Services\Integrations\FacebookLeadAds;
use App\Services\Integrations\FacebookTokenException;
use App\Services\Integrations\FacebookLeadImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * One "leadgen" webhook event (Api\FacebookWebhookController): reads the lead from Facebook with the
 * connected Page's token and adds it to that account's CRM (FacebookLeadImporter). Retried on failure.
 */
class ImportFacebookLead implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;
    public array $backoff = [60, 300];

    public function __construct(public string $pageId, public string $leadgenId)
    {
    }

    public function handle(FacebookLeadAds $facebook, FacebookLeadImporter $importer, ?FacebookConnectionHealth $health = null): void
    {
        $connection = FacebookPageConnection::where('page_id', $this->pageId)->first();
        if (!$connection) {
            Log::info("Facebook lead {$this->leadgenId} for page {$this->pageId}: page isn't connected — ignored.");

            return;
        }
        if (FacebookLead::where('leadgen_id', $this->leadgenId)->exists()) {
            return;
        }

        try {
            $importer->import($connection, $facebook->lead($this->leadgenId, $connection->page_access_token));
            if ($connection->last_error) {
                $connection->forceFill(['last_error' => null])->save();
            }
        } catch (FacebookTokenException $e) {
            // Retrying can't help: flag the Page and email its owner to reconnect. "Sync now" after
            // reconnecting picks this lead up.
            ($health ?? app(FacebookConnectionHealth::class))->tokenFailed($connection, $e);
        } catch (\Throwable $e) {
            $connection->forceFill(['last_error' => $e->getMessage()])->save();
            throw $e;
        }
    }
}
