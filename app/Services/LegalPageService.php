<?php

namespace App\Services;

use App\Models\CmsKit\SiteInformation;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the payload for the 4 legal pages (Terms of Use, Privacy Policy, Security Policy,
 * Cookie Settings) — one function serving all 4 URLs, keyed by a short internal slug decoupled
 * from the route path. Content is authored once in the admin's Site Information > Legal Content
 * section and reused here verbatim.
 */
class LegalPageService
{
    private const CACHE_TTL = 180; // seconds

    private const PAGES = [
        'terms' => ['title' => 'Terms and Conditions', 'field' => 'terms_and_conditions'],
        'privacy' => ['title' => 'Privacy Policy', 'field' => 'privacy_policy'],
        'security' => ['title' => 'Security Policy', 'extra' => 'security_settings'],
        'cookie' => ['title' => 'Cookie Settings', 'extra' => 'cookie_policy'],
    ];

    public function getPage(string $key, string $lang): ?array
    {
        if (!isset(self::PAGES[$key])) {
            return null;
        }

        return Cache::remember("legal-page:{$key}:{$lang}", self::CACHE_TTL, function () use ($key, $lang) {
            $config = self::PAGES[$key];
            $info = SiteInformation::first();

            $content = isset($config['field'])
                ? $info?->getTranslation($config['field'], $lang)
                : $info?->getExtraFieldTranslation($config['extra'], $lang);

            return [
                'key' => $key,
                'title' => $config['title'],
                'content' => $content,
            ];
        });
    }
}
