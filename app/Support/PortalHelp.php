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
        'portal.dashboard' => 'dashboard',
        'portal.crm.leads.*' => 'leads',
        'portal.crm.lead-insights.*' => 'lead-insights',
        'portal.crm.website-leads.*' => 'website-leads',
        'portal.crm.master.stages.*' => 'stages',
        'portal.crm.master.tags.*' => 'tags',
        'portal.crm.master.sources.*' => 'sources',
        'portal.crm.master.property-options.*' => 'property-options',
        'portal.crm.integrations.facebook*' => 'facebook',
        'portal.crm.integrations.property-finder.*' => 'property-finder',
        'portal.crm.integrations.*' => 'integrations',
        'portal.crm.reports.*' => 'reports',
        'portal.properties.create' => 'property-form',
        'portal.properties.edit' => 'property-form',
        'portal.properties.*' => 'properties',
        'portal.commercial.*' => 'commercial',
        'portal.listing-approvals.*' => 'listing-approvals',
        'portal.featured.*' => 'premium',
        'portal.sold.*' => 'sold',
        'portal.marketing.*' => 'marketing',
        'portal.agency.*' => 'agency',
        'portal.agents.*' => 'agents',
        'portal.nearby-places.*' => 'nearby-places',
        'portal.watermark.*' => 'watermark',
        'portal.contact.*' => 'support',
        'portal.profile.*' => 'profile',
        'portal.security' => 'security',
        'portal.two-factor.*' => 'security',
        'portal.plans.*' => 'plans',
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
