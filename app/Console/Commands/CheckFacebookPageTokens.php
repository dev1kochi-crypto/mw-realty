<?php

namespace App\Console\Commands;

use App\Models\FacebookPageConnection;
use App\Services\Integrations\FacebookConnectionHealth;
use App\Services\Integrations\FacebookLeadAds;
use App\Services\Integrations\FacebookTokenException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Daily: checks each connected Facebook Page's token still works, so a Page that stopped working is
 * flagged and its owner emailed to reconnect (FacebookConnectionHealth) even before a lead is lost.
 */
class CheckFacebookPageTokens extends Command
{
    protected $signature = 'facebook:check-page-tokens';

    protected $description = 'Flag Facebook Pages whose access token stopped working and email their owners to reconnect';

    public function handle(FacebookLeadAds $facebook, FacebookConnectionHealth $health): int
    {
        if (!$facebook->configured()) {
            return self::SUCCESS;
        }

        $broken = 0;
        FacebookPageConnection::whereNull('needs_reconnect_at')->with('owner')->chunkById(100, function ($connections) use ($facebook, $health, &$broken) {
            foreach ($connections as $connection) {
                try {
                    $facebook->checkPageToken($connection->page_id, $connection->page_access_token);
                } catch (FacebookTokenException $e) {
                    $health->tokenFailed($connection, $e);
                    $broken++;
                } catch (\Throwable $e) {
                    Log::info("Facebook token check for page {$connection->page_id} failed: " . $e->getMessage()); // network etc. — try tomorrow
                }
            }
        });

        $this->info("{$broken} Facebook Page(s) need reconnecting.");

        return self::SUCCESS;
    }
}
