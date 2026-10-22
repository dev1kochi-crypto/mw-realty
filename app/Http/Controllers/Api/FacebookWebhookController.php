<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ImportFacebookLead;
use App\Services\Integrations\FacebookLeadAds;
use Illuminate\Http\Request;

/**
 * Facebook's "Page" webhook for Lead Ads (callback URL: /api/webhooks/facebook, field "leadgen").
 * GET answers Facebook's verification handshake; POST receives new leads, checked against the app
 * secret's signature, and queues each one (ImportFacebookLead) so Facebook gets its 200 at once.
 */
class FacebookWebhookController extends Controller
{
    public function verify(Request $request)
    {
        $token = (string) config('services.facebook_leads.verify_token');

        if ($token !== '' && $request->query('hub_mode') === 'subscribe' && hash_equals($token, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request, FacebookLeadAds $facebook)
    {
        if (!$facebook->validSignature($request->getContent(), $request->header('X-Hub-Signature-256'))) {
            return response('Invalid signature', 403);
        }

        if ($request->input('object') === 'page') {
            foreach ((array) $request->input('entry', []) as $entry) {
                foreach ((array) ($entry['changes'] ?? []) as $change) {
                    $value = $change['value'] ?? [];
                    if (($change['field'] ?? null) === 'leadgen' && !empty($value['leadgen_id'])) {
                        ImportFacebookLead::dispatch((string) ($value['page_id'] ?? $entry['id'] ?? ''), (string) $value['leadgen_id']);
                    }
                }
            }
        }

        return response('EVENT_RECEIVED', 200);
    }
}
