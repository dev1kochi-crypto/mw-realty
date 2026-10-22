<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FacebookDataDeletionRequest;
use App\Models\FacebookPageConnection;
use App\Services\Integrations\FacebookLeadAds;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Facebook's "Data deletion callback URL" (Meta app › Settings › Basic): /api/webhooks/facebook/data-deletion.
 * When a user removes the app and asks for their data to be deleted, Facebook POSTs a signed_request
 * naming them. The only data held about a Facebook user is the Pages they connected (with Page access
 * tokens), so those are unsubscribed and removed — leads already in the CRM belong to the agency and stay.
 * Facebook shows the user the returned confirmation code and status URL (/facebook/data-deletion?code=…).
 */
class FacebookDataDeletionController extends Controller
{
    public function callback(Request $request, FacebookLeadAds $facebook)
    {
        $data = $facebook->parseSignedRequest((string) $request->input('signed_request'));
        $userId = (string) ($data['user_id'] ?? '');
        if ($userId === '') {
            return response()->json(['error' => 'Invalid signed_request.'], 400);
        }

        $connections = FacebookPageConnection::where('facebook_user_id', $userId)->get();
        foreach ($connections as $connection) {
            try {
                $facebook->unsubscribe($connection->page_id, $connection->page_access_token);
            } catch (\Throwable $e) {
                Log::info("Facebook unsubscribe for page {$connection->page_id} failed: " . $e->getMessage());
            }
            $connection->delete();
        }

        $deletion = FacebookDataDeletionRequest::create([
            'confirmation_code' => Str::upper(Str::random(12)),
            'facebook_user_id' => $userId,
            'pages_removed' => $connections->count(),
            'completed_at' => now(),
        ]);

        return response()->json([
            'url' => route('facebook.data-deletion.status', ['code' => $deletion->confirmation_code]),
            'confirmation_code' => $deletion->confirmation_code,
        ]);
    }

    /** Public status page Facebook links the user to, by confirmation code. */
    public function status(Request $request)
    {
        $code = (string) $request->query('code');
        $deletion = $code !== '' ? FacebookDataDeletionRequest::where('confirmation_code', $code)->first() : null;

        return response()->view('facebook-data-deletion', ['code' => $code, 'deletion' => $deletion], $deletion || $code === '' ? 200 : 404);
    }
}
