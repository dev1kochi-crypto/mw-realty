<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Roles and Permissions
    |--------------------------------------------------------------------------
    |
    | This configuration defines the default roles and their module-level
    | permissions. The seeder uses this to populate the database.
    |
    */

    'roles' => [
        'superadmin' => [
            'name' => 'Super Admin',
            'permissions' => '*' // Special flag for all permissions
        ],
        'client' => [
            'name' => 'Client',
            'permissions' => [
                'testimonials.view',
                'testimonials.edit',
                'site-information.view',
                'site-information.edit',
                'languages.view',
                'metadata.view',
                'faqs.view',
                'faqs.edit',
                'enquiries.view',
                'enquiries.edit',
                'careers.view',
            ]
        ],
    ],

    'enquiries' => [
        'columns' => [
            'name' => true,
            'email' => true,
            'phone' => true,
            'company' => true,
            'country' => true,
            'page_source' => true,
            'page_url' => true,
            'message' => true,
            'subject' => true,
            'created_at' => true,
        ],
        'extra_fields' => [
            'subject' => ['label' => 'Subject', 'type' => 'text'],
            'interested_in' => ['label' => 'Interested In', 'type' => 'text'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Users
    |--------------------------------------------------------------------------
    |
    | Seed these users automatically via CmsRolesPermissionsSeeder, so a fresh
    | `migrate` + `db:seed` always has a working superadmin login without an
    | extra manual step. DEV/DEMO CONVENIENCE ONLY — change or remove this
    | before any real deploy; `php artisan admin:provision {email}` sets a
    | password interactively without ever writing it to a file, for that.
    |
    */
    'users' => [
        [
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'superadmin',
        ],
    ],

    'defaults' => [
        'view',
        'edit',
        'create',
        'delete'
    ]
];
