<?php

namespace App\Services\PropertyFinder;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Property Finder Enterprise API (Atlas): an account's API key + secret → a short-lived access
 * token (POST /v1/auth/token, cached per key) → its listings, page by page (GET /v1/listings).
 * The response envelope and field names are read defensively — see PropertyFinderListing.
 */
class PropertyFinderClient
{
    public const PER_PAGE = 50;

    public function __construct(private readonly string $apiKey, private readonly string $apiSecret)
    {
    }

    /** Throws PropertyFinderException when the key / secret is rejected or the API is unreachable. */
    public function verify(): void
    {
        $this->token(fresh: true);
    }

    /**
     * One page of listings: ['items' => array[], 'hasMore' => bool]. Newest first when the API
     * honours the sort, so a "new listings only" sync can stop early.
     */
    public function listings(int $page): array
    {
        $response = $this->request()->get('/v1/listings', [
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'orderBy' => 'createdAt',
            'orderDirection' => 'desc',
        ]);
        if ($response->status() === 401) {
            // Token expired early — log in again once.
            Cache::forget($this->tokenKey());
            $response = $this->request()->get('/v1/listings', ['page' => $page, 'perPage' => self::PER_PAGE, 'orderBy' => 'createdAt', 'orderDirection' => 'desc']);
        }
        $this->failOn($response, 'Could not read your Property Finder listings');

        $json = $response->json() ?? [];
        $items = array_is_list($json) ? $json : ($json['results'] ?? $json['data'] ?? $json['listings'] ?? $json['items'] ?? []);
        $pagination = $json['pagination'] ?? $json['meta']['pagination'] ?? $json['meta'] ?? [];
        $totalPages = (int) ($pagination['totalPages'] ?? $pagination['total_pages'] ?? $pagination['lastPage'] ?? $pagination['last_page'] ?? 0);

        return [
            'items' => array_values(array_filter((array) $items, 'is_array')),
            'hasMore' => $totalPages ? $page < $totalPages : count((array) $items) >= self::PER_PAGE,
        ];
    }

    /** A location's name and its parents ("Dubai Marina › Dubai"), cached — listings only carry its id. */
    public function location(int|string $id): ?array
    {
        return Cache::remember("property_finder.location.v2.{$id}", now()->addDay(), function () use ($id) {
            // GET /v1/locations?filter[id]= → {data: [{id, name, coordinates, tree: [city, community, …]}]}
            // (/v1/locations/{id} isn't open to API keys).
            $response = $this->request()->get('/v1/locations', ['filter[id]' => $id]);
            if (!$response->successful()) {
                return null;
            }
            $json = $response->json() ?? [];
            $location = $json['data'] ?? $json['results'] ?? $json;
            if (is_array($location) && array_is_list($location)) {
                $location = $location[0] ?? null;
            }

            return is_array($location) ? $location : null;
        });
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(self::baseUrl())
            ->acceptJson()->timeout(30)->retry(2, 500, throw: false)
            ->withToken($this->token());
    }

    private function token(bool $fresh = false): string
    {
        if ($fresh) {
            Cache::forget($this->tokenKey());
        }

        return Cache::remember($this->tokenKey(), now()->addMinutes(20), function () {
            try {
                $response = Http::baseUrl(self::baseUrl())
                    ->acceptJson()->timeout(20)
                    ->post('/v1/auth/token', ['apiKey' => $this->apiKey, 'apiSecret' => $this->apiSecret]);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                throw new PropertyFinderException('Property Finder could not be reached — try again in a few minutes.', previous: $e);
            }
            if (in_array($response->status(), [400, 401, 403], true)) {
                throw new PropertyFinderException('Property Finder rejected the API key or secret — check them in PF Expert › Settings › API.');
            }
            $this->failOn($response, 'Property Finder login failed');

            $json = $response->json() ?? [];
            $token = $json['accessToken'] ?? $json['access_token'] ?? $json['token'] ?? $json['data']['accessToken'] ?? null;
            if (!$token) {
                throw new PropertyFinderException('Property Finder login returned no access token.');
            }

            return (string) $token;
        });
    }

    /** Overridable with PROPERTY_FINDER_API_URL; the default also covers a config cached before this setting existed. */
    private static function baseUrl(): string
    {
        return rtrim((string) (config('services.property_finder.base_url') ?: 'https://atlas.propertyfinder.com'), '/');
    }

    private function tokenKey(): string
    {
        return 'property_finder.token.' . sha1($this->apiKey . '|' . $this->apiSecret);
    }

    private function failOn(\Illuminate\Http\Client\Response $response, string $what): void
    {
        if ($response->successful()) {
            return;
        }
        $detail = $response->json('detail') ?? $response->json('message') ?? $response->json('title') ?? $response->reason();

        throw new PropertyFinderException("{$what} (HTTP {$response->status()}): {$detail}");
    }
}
