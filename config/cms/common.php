<?php

return [
    'name' => 'MW REALTY',
    'theme' => [
        'primary_color' => '#04a1cc',
        'primary_gradient' => '',
        'secondary_color' => '#264373',
        'background_color' => '#f2ecec',
        'sidebar_color' => '#343a40',
        'text_color' => '#212529',
    ],

    'auth' => [
        'admin_name' => env('CMS_ADMIN_NAME', 'Admin User'),
        'admin_email' => env('CMS_ADMIN_EMAIL', 'admin@example.com'),
        'prefix' => 'admin',
        'middleware' => ['web'],
    ],

    'modules' => [
        'banners' => true,
        'filters' => true,
        'communities' => true,
        'find-properties' => true,
        'brands' => true,
        'post-property-steps' => true,
        'popular-places' => true,
        'luxury-projects' => true,
        'about-us' => true,
        'market-trends' => true,
        'why-choose-us' => true,
        'our-builders' => true,
        'connect-us' => true,
        'contact-us' => true,
        'testimonials' => true,
        'languages' => true,
        'metadata' => true,
        'site-information' => true,
        'sitemap' => true,
        'faqs' => false,
        'enquiries' => true,
        'locations' => false,
        'newsletter-signups' => true,
        'blogs' => true,
        'careers' => false,
        'properties' => true,
        'portal-accounts' => true,
        'plans' => true,
    ],

    'careers' => [
        'common_section' => true,
        'vacancies' => true,
        'departments' => true,
        'candidates' => true,
    ],

    'tinymce' => [
        'selector' => '.tinymce-editor',
        'plugins' => 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
        'toolbar' => 'undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
    ],
];
