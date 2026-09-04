<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Observed Models
    |--------------------------------------------------------------------------
    |
    | List the models that should trigger an automated sitemap update when
    | they are created, updated, or deleted.
    |
    */

    'models' => [
        // Simple format (requires getSitemapUrl() or 'url' attribute on model)
        // \CMS\SiteManager\Models\CmsKit\Testimonial::class,

        // Dynamic format (specify URL prefix and optionally slug field)
        // \App\Models\Service::class => [
        //     'url_prefix' => '/services/',
        //     'slug_field' => 'slug', // optional, defaults to 'slug'
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Middleware
    |--------------------------------------------------------------------------
    |
    | Define the middleware that should be applied to the sitemap admin routes.
    | Default is ['web', 'cms.auth'].
    |
    */

    'middleware' => ['web', 'cms.auth'],

];

