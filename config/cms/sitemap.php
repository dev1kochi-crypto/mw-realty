<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Observed Models (cms-kit package)
    |--------------------------------------------------------------------------
    |
    | Left empty on purpose: the package's own observer queues one partial
    | update per save, with the model serialized into the job (which fails for
    | deletions). This site regenerates the whole sitemap + llms.txt from the
    | database instead — see 'sources' and 'regenerate_on_change' below and
    | App\Services\Seo\SiteSitemapService.
    |
    */

    'models' => [],

    /*
    |--------------------------------------------------------------------------
    | Route Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => ['web', 'cms.auth'],

    /*
    |--------------------------------------------------------------------------
    | Static pages
    |--------------------------------------------------------------------------
    |
    | Fixed public pages of the Vue site. path => [priority, changefreq, llms title].
    | Login / signup / profile / thank-you / map views are deliberately left out.
    |
    */

    'static_pages' => [
        '/' => [1.0, 'daily', 'Home'],
        '/properties' => [0.9, 'daily', 'Properties for sale & rent'],
        '/commercial' => [0.8, 'daily', 'Commercial properties'],
        '/premium-properties' => [0.8, 'daily', 'Premium properties'],
        '/agents' => [0.7, 'weekly', 'Real estate agents'],
        '/agencies' => [0.7, 'weekly', 'Real estate agencies'],
        '/market-insights' => [0.7, 'weekly', 'Market insights'],
        '/blogs' => [0.6, 'weekly', 'Blog'],
        '/careers' => [0.5, 'weekly', 'Careers'],
        '/about' => [0.5, 'monthly', 'About us'],
        '/contact' => [0.5, 'monthly', 'Contact us'],
        '/terms-and-conditions' => [0.2, 'yearly', 'Terms and conditions'],
        '/privacy-policy' => [0.2, 'yearly', 'Privacy policy'],
        '/security-policy' => [0.2, 'yearly', 'Security policy'],
        '/cookie-settings' => [0.1, 'yearly', 'Cookie settings'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database sources
    |--------------------------------------------------------------------------
    |
    | One entry per kind of detail page. Only rows matching 'where' (the same
    | rules the public pages use to decide what's visible) are listed.
    |   path        URL pattern — {slug} is replaced with the row's slug
    |   section     heading used in llms.txt
    |   title       fields tried in order (translated first, then plain attribute)
    |   description fields tried in order (HTML stripped, ~180 chars)
    |   llms_limit  optional — only the N most recently updated rows go into llms.txt
    |   llms_more   optional [path, label] — "see all" link after a limited section
    |
    */

    'sources' => [
        'properties' => [
            'model' => \App\Models\Property::class,
            'where' => ['status' => true],
            'path' => '/property-details/{slug}',
            'priority' => 0.8,
            'changefreq' => 'weekly',
            'section' => 'Properties',
            'title' => ['title'],
            'description' => ['description', 'key_features'],
            // llms.txt should stay a short index — list the newest N here (the sitemap has them all).
            'llms_limit' => 100,
            'llms_more' => ['/properties', 'All properties for sale & rent'],
        ],
        'agents' => [
            'model' => \App\Models\PortalUser::class,
            'where' => ['type' => 'agent', 'status' => 'approved', 'is_active' => true],
            'path' => '/agent-details/{slug}',
            'priority' => 0.6,
            'changefreq' => 'weekly',
            'section' => 'Agents',
            'title' => ['name'],
            'description' => ['bio'],
        ],
        'agencies' => [
            'model' => \App\Models\PortalUser::class,
            'where' => ['type' => 'company', 'status' => 'approved', 'is_active' => true],
            'path' => '/agency-details/{slug}',
            'priority' => 0.6,
            'changefreq' => 'weekly',
            'section' => 'Agencies',
            'title' => ['company_name', 'name'],
            'description' => ['bio'],
        ],
        'blogs' => [
            'model' => \App\Models\CmsKit\Blog::class,
            'where' => ['status' => true],
            'path' => '/blog-details/{slug}',
            'priority' => 0.6,
            'changefreq' => 'monthly',
            'section' => 'Blog',
            'title' => ['title'],
            'description' => ['short_description', 'excerpt', 'description'],
        ],
        'market_insights' => [
            'model' => \App\Models\CmsKit\MarketInsight::class,
            'where' => ['status' => true],
            'path' => '/market-insights/{slug}',
            'priority' => 0.6,
            'changefreq' => 'monthly',
            'section' => 'Market Insights',
            'title' => ['title'],
            'description' => ['summary', 'excerpt', 'short_description', 'description'],
        ],
        'careers' => [
            'model' => \App\Models\CmsKit\Career::class,
            'where' => ['status' => true],
            'path' => '/careers/{slug}',
            'priority' => 0.4,
            'changefreq' => 'weekly',
            'section' => 'Careers',
            'title' => ['title'],
            'description' => ['short_description', 'description'],
        ],
        'landing_pages' => [
            'model' => \App\Models\CmsKit\LandingPage::class,
            'where' => ['status' => true],
            'path' => '/{slug}',
            'priority' => 0.5,
            'changefreq' => 'monthly',
            'section' => 'Campaigns',
            'title' => ['title'],
            'description' => ['description', 'subtitle'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Regenerate on change
    |--------------------------------------------------------------------------
    |
    | Saving / deleting any of these models queues one sitemap.xml + llms.txt
    | rebuild (bursts within a minute collapse into a single rebuild).
    |
    */

    // Off in the test suite (phpunit.xml) so tests never rewrite the real public/ files.
    'auto_regenerate' => (bool) env('SITEMAP_AUTO_REGENERATE', true),

    'regenerate_on_change' => [
        \App\Models\Property::class,
        \App\Models\PortalUser::class,
        \App\Models\CmsKit\Blog::class,
        \App\Models\CmsKit\MarketInsight::class,
        \App\Models\CmsKit\Career::class,
        \App\Models\CmsKit\LandingPage::class,
    ],

];
