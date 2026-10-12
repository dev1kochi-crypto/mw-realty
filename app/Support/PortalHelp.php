<?php

namespace App\Support;

use App\Models\HelpGuide;
use Illuminate\Http\Request;

/**
 * Portal help guides — one HelpGuide row per module (title, icon, intro, features, steps, tips),
 * managed in Admin › Help Desk. Shown from the top bar's "!" icon and opened automatically the
 * first time a user visits. Inactive guides are treated as missing.
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
        return self::find($topic) !== null;
    }

    public static function load(string $topic): ?array
    {
        return self::find($topic)?->toGuide();
    }

    private static function find(string $topic): ?HelpGuide
    {
        if (preg_match('/^[a-z0-9-]+$/', $topic) !== 1) {
            return null;
        }

        return HelpGuide::active()->where('topic', $topic)->first();
    }
}
