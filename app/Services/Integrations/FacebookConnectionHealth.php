<?php

namespace App\Services\Integrations;

use App\Mail\FacebookPageReconnectMail;
use App\Models\FacebookPageConnection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * A connected Facebook Page whose token Facebook rejected (FacebookTokenException): flag it as needing
 * reconnection and email its account owner once — with the reason and how to reconnect. Reconnecting
 * (IntegrationController::storePages) clears the flag.
 */
class FacebookConnectionHealth
{
    public function tokenFailed(FacebookPageConnection $connection, FacebookTokenException $e): void
    {
        $connection->forceFill([
            'last_error' => $e->getMessage(),
            'needs_reconnect_at' => $connection->needs_reconnect_at ?? now(),
        ])->save();

        if ($connection->reconnect_notified_at || !($owner = $connection->owner)) {
            return;
        }

        try {
            Mail::to($owner->email)->queue((new FacebookPageReconnectMail($connection, $e->reason(), $e->getMessage()))->afterCommit());
            $connection->forceFill(['reconnect_notified_at' => now()])->save();
        } catch (\Throwable $mailError) {
            Log::error("Facebook reconnect email for page {$connection->page_id} failed: " . $mailError->getMessage());
        }
    }

    /** Fresh token saved — the Page works again and a future breakage emails again. */
    public function reconnected(FacebookPageConnection $connection): void
    {
        $connection->forceFill(['needs_reconnect_at' => null, 'reconnect_notified_at' => null, 'last_error' => null])->save();
    }
}
