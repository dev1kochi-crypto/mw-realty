<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Facebook Graph API calls for the Lead Ads integration (config/services.php "facebook_leads"):
 * Facebook Login for connecting Pages, subscribing a Page to the app's "leadgen" webhook, reading
 * leads, and checking webhook signatures. Failures throw RuntimeException with Facebook's message.
 */
class FacebookLeadAds
{
    /** Permissions asked for at Facebook Login: list the user's Pages, subscribe them, read their leads (+ ad names). */
    public const SCOPES = ['pages_show_list', 'pages_read_engagement', 'pages_manage_metadata', 'leads_retrieval', 'ads_read'];

    /** Lead fields read from Facebook. The ad / campaign ones need ads_read — see lead(). */
    private const LEAD_FIELDS = 'id,created_time,field_data,form_id,ad_id,ad_name,adset_name,campaign_name,platform,is_organic';
    private const LEAD_FIELDS_BASIC = 'id,created_time,field_data,form_id,ad_id,platform,is_organic';

    public function configured(): bool
    {
        return filled(config('services.facebook_leads.app_id')) && filled(config('services.facebook_leads.app_secret'));
    }

    public function loginUrl(string $redirectUri, string $state): string
    {
        return 'https://www.facebook.com/' . $this->version() . '/dialog/oauth?' . http_build_query([
            'client_id' => config('services.facebook_leads.app_id'),
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => implode(',', self::SCOPES),
            'response_type' => 'code',
        ]);
    }

    /** Login code → long-lived user token (Page tokens read with it don't expire). */
    public function userToken(string $code, string $redirectUri): string
    {
        $short = $this->get('oauth/access_token', [
            'client_id' => config('services.facebook_leads.app_id'),
            'client_secret' => config('services.facebook_leads.app_secret'),
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ])['access_token'] ?? null;
        if (!$short) {
            throw new RuntimeException('Facebook did not return an access token.');
        }

        return $this->get('oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => config('services.facebook_leads.app_id'),
            'client_secret' => config('services.facebook_leads.app_secret'),
            'fb_exchange_token' => $short,
        ])['access_token'] ?? $short;
    }

    /** @return array<int, array{id: string, name: string, access_token: string, picture: ?string}> Pages the user manages. */
    public function pages(string $userToken): array
    {
        $pages = [];
        $params = ['fields' => 'id,name,access_token,picture{url}', 'limit' => 100, 'access_token' => $userToken];
        foreach ($this->paginate('me/accounts', $params) as $page) {
            if (!empty($page['access_token'])) {
                $pages[] = ['id' => (string) $page['id'], 'name' => $page['name'] ?? $page['id'], 'access_token' => $page['access_token'], 'picture' => $page['picture']['data']['url'] ?? null];
            }
        }

        return $pages;
    }

    /** Sends the Page's new leads to this app's webhook. */
    public function subscribe(string $pageId, string $pageToken): void
    {
        $result = $this->post("{$pageId}/subscribed_apps", ['subscribed_fields' => 'leadgen', 'access_token' => $pageToken]);
        if (empty($result['success'])) {
            throw new RuntimeException('Facebook did not confirm the webhook subscription.');
        }
    }

    public function unsubscribe(string $pageId, string $pageToken): void
    {
        $this->request()->delete($this->url("{$pageId}/subscribed_apps"), ['access_token' => $pageToken]);
    }

    /** Cheap call with the Page token — throws FacebookTokenException once Facebook stops accepting it. */
    public function checkPageToken(string $pageId, string $pageToken): void
    {
        $this->get($pageId, ['fields' => 'id', 'access_token' => $pageToken]);
    }

    /** One lead by its leadgen id. Without ads_read the ad / campaign names are left out. */
    public function lead(string $leadgenId, string $pageToken): array
    {
        try {
            return $this->get($leadgenId, ['fields' => self::LEAD_FIELDS, 'access_token' => $pageToken]);
        } catch (FacebookTokenException $e) {
            throw $e;
        } catch (RuntimeException) {
            return $this->get($leadgenId, ['fields' => self::LEAD_FIELDS_BASIC, 'access_token' => $pageToken]);
        }
    }

    /** @return array<int, array{id: string, name: ?string}> The Page's lead forms. */
    public function forms(string $pageId, string $pageToken): array
    {
        return array_map(fn ($f) => ['id' => (string) $f['id'], 'name' => $f['name'] ?? null],
            iterator_to_array($this->paginate("{$pageId}/leadgen_forms", ['fields' => 'id,name', 'limit' => 100, 'access_token' => $pageToken]), false));
    }

    /** Leads of a form created after $since (Unix time), newest first. */
    public function formLeads(string $formId, string $pageToken, int $since): \Generator
    {
        $filter = json_encode([['field' => 'time_created', 'operator' => 'GREATER_THAN', 'value' => $since]]);
        try {
            yield from $this->paginate("{$formId}/leads", ['fields' => self::LEAD_FIELDS, 'filtering' => $filter, 'limit' => 100, 'access_token' => $pageToken]);
        } catch (FacebookTokenException $e) {
            throw $e;
        } catch (RuntimeException) {
            yield from $this->paginate("{$formId}/leads", ['fields' => self::LEAD_FIELDS_BASIC, 'filtering' => $filter, 'limit' => 100, 'access_token' => $pageToken]);
        }
    }

    /** Ad name for an ad id (when the lead itself didn't carry it). */
    public function adName(string $adId, string $token): ?string
    {
        try {
            return $this->get($adId, ['fields' => 'name', 'access_token' => $token])['name'] ?? null;
        } catch (RuntimeException) {
            return null;
        }
    }

    /** Webhook request really from Facebook: X-Hub-Signature-256 = sha256 HMAC of the raw body with the app secret. */
    public function validSignature(string $rawBody, ?string $header): bool
    {
        $secret = (string) config('services.facebook_leads.app_secret');
        if ($secret === '' || !$header || !str_starts_with($header, 'sha256=')) {
            return false;
        }

        return hash_equals('sha256=' . hash_hmac('sha256', $rawBody, $secret), $header);
    }

    private function paginate(string $path, array $params): \Generator
    {
        $response = $this->get($path, $params);
        while (true) {
            foreach ($response['data'] ?? [] as $row) {
                yield $row;
            }
            $next = $response['paging']['next'] ?? null;
            if (!$next) {
                return;
            }
            $response = $this->send(fn () => $this->request()->get($next));
        }
    }

    private function get(string $path, array $params): array
    {
        return $this->send(fn () => $this->request()->get($this->url($path), $params));
    }

    private function post(string $path, array $params): array
    {
        return $this->send(fn () => $this->request()->asForm()->post($this->url($path), $params));
    }

    private function send(callable $call): array
    {
        $response = $call();
        $json = $response->json() ?? [];
        if ($response->failed() || isset($json['error'])) {
            $code = (int) ($json['error']['code'] ?? 0);
            if (in_array($code, FacebookTokenException::CODES, true)) {
                throw new FacebookTokenException($json['error']['message'] ?? 'Facebook rejected the access token.', $code);
            }
            throw new RuntimeException($json['error']['message'] ?? ('Facebook request failed (HTTP ' . $response->status() . ').'));
        }

        return $json;
    }

    private function request(): PendingRequest
    {
        return Http::timeout(20)->acceptJson();
    }

    private function url(string $path): string
    {
        return 'https://graph.facebook.com/' . $this->version() . '/' . ltrim($path, '/');
    }

    private function version(): string
    {
        return (string) config('services.facebook_leads.graph_version', 'v21.0');
    }
}
