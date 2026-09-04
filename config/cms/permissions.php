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
    | Seed these users automatically. 
    |
    */
    'users' => [
        [
            'name'     => 'Super Admin',
            'email'    => 'admin@example.com',
            'password' => 'password',
            'role'     => 'superadmin',
        ],
        [
            'name'     => 'Client User',
            'email'    => 'client@example.com',
            'password' => 'password',
            'role'     => 'client',
        ],
    ],

    'defaults' => [
        'view',
        'edit',
        'create',
        'delete'
    ]
];
