<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Portal help guides — one content file per module in resources/help/portal/{topic}.php
 * (title, icon, intro, features, steps, tips), shown from the top bar's "!" icon and opened
 * automatically the first time a user visits that module.
 */
class PortalHelp
{
    /** Saved on the account the first time a guide opens by itself: none opens by itself again, on any page. */
    public const DISMISSED = 'all';

    /** Route name pattern => topic. First match wins, so specific pages come before their module. */
    private const ROUTES = [
        'portal.agency.*' => 'agency',
    ];

    public static function topicFor(Request $request): ?string
    {
        foreach (self::ROUTES as $pattern => $topic) {
            if ($request->routeIs($pattern)) {
                return self::exists($topic) ? $topic : null;
            }
        }

        return null;
    }

    public static function exists(string $topic): bool
    {
        return preg_match('/^[a-z0-9-]+$/', $topic) === 1 && is_file(self::path($topic));
    }

    public static function load(string $topic): ?array
    {
        return self::exists($topic) ? require self::path($topic) : null;
    }

    private static function path(string $topic): string
    {
        return resource_path("help/portal/{$topic}.php");
    }
}
