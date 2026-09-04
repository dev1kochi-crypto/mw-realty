<?php

/**
 * CMS KIT DATABASE CONFIGURATION
 *
 * Extra-field field reference moved to:
 *   docs/extra-fields.md
 *
 * Add/modify banner extra fields in:
 *   config/cms/database.php -> banners.items.extra_fields
 */
return [
    'languages' => [
        'items' => [
            'flag' => true,
            'flag_alt' => true,
            'required' => [],
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
       
    ],
    'testimonials' => [
        'section' => [
            'title' => true,
            'sub_heading_1' => true,
            'sub_heading_2' => true,
            'section_image' => true,
            'section_image_alt' => true,
            'banner' => true,
            'banner_alt' => true,
            'description' => true,
            'display_home' => true,
            'extra_fields' => [],
            'required' => ['title'], // Section fields that are mandatory
        ],
        'items' => [
            'name' => true,
            'designation' => true,
            'content' => true,
            'rating' => true,
            'order' => true,
            'status' => true,
            'image' => true,
            'image_alt' => true,
            'extra_fields' => [],
            'required' => ['name', 'content'], // Fields that are mandatory
        ],
    ],
    'banners' => [
        'max_items' => 5, // Dynamic limit for banners
        'allowed_types' => ['image', 'video'], // Available banner types
        'items' => [
            'banner_type' => true, // New: image, video toggle
            'line_1' => true,
            'line_2' => true,
            'content' => true,
            'image' => true,
            'video_url' => true, // New: for video banners
            'video_file' => true, // New: for video file uploads
            'image_alt' => true,
            'buttons' => true, // New: JSON array of buttons
            'additional_buttons'=> true, // New: toggle for additional buttons
            'google_review_text' => true, // New
            'google_rating' => true, // New
            'google_review_count' => true, // New
            'google_avatars' => true, // New: for the avatars in screenshot
            'google_avatars_alt' => true, // New: alt text for avatars
            'order' => true,
            'status' => true,
            'extra_fields' => [],
            'required' => ['line_1', 'banner_type'], // Mandatory fields
        ],
    ],
    'metadata' => [
        'required' => ['meta_title', 'meta_description'], // SEO fields that are mandatory
    ],
    'site-information' => [
        'company_name' => true,
        'address' => true,
        'country' => true,
        'po_box' => true,
        'fax' => true,
        'phone_1' => true,
        'phone_2' => true,
        'phone_3' => true,
        'phone_4' => true,
        'whatsapp_number' => true,
        'email_1' => true,
        'email_2' => true,
        'email_3' => true,
        'email_4' => true,
        'receipt_email' => true,
        'privacy_policy' => true,
        'terms_and_conditions' => true,
        'disclaimer' => true,
        'logo' => true,
        'logo_alt' => true,
        'footer_logo' => true,
        'footer_logo_alt' => true,
        'footer_description' => true,
        'facebook' => true,
        'twitter' => true,
        'linkedin' => true,
        'instagram' => true,
        'tiktok' => true,
        'snapchat' => true,
        'pinterest' => true,
        'youtube' => true,
        'skype' => true,
        'whatsapp_social' => true,
        'vimeo' => true,
        'gtag' => true,
        'custom_head_script' => true,
        'custom_body_script' => true,
        'extra_fields' => [],
        'required' => ['company_name', 'address', 'phone_1', 'email_1', 'receipt_email', 'logo', 'favicon'], // Fields that are mandatory
    ],
    'locations' => [
        'section' => [
            'title' => true,
            'description' => true,
            'status' => true,
            'extra_fields' => [],
        ],
        'items' => [
            'title' => true,
            'image' => true,
            'flag' => true,
            'phone' => true,
            'whatsapp' => true,
            'fax' => true,
            'emails' => true,
            'address' => true,
            'country' => true,
            'map_link' => true,
            'order' => true,
            'status' => true,
            'extra_fields' => [],
            'required' => ['title', 'address'],

        ],
    ],
    'brands' => [
        'items' => [
            'image' => true,
            'image_alt' => true,
            'order' => true,
            'status' => true,
            'extra_fields' => [],
            'required' => ['image', 'image_alt'],
        ],
    ],
    'blogs' => [
        'section' => [
            'title' => true,
            'listing_title' => true,
            'description' => true,
            'banner' => true,
            'banner_alt' => true,
            'status' => true,
            'display_home' => true,
            'extra_fields' => [],
            'required' => ['title'],
        ],
        'items' => [
            'title' => true,
            'slug' => true,
            'content' => true,
            'published_at' => true,
            'feature_image' => true,
            'feature_image_alt' => true,
            'detail_image' => true,
            'detail_image_alt' => true,
            'banner_image' => true,
            'banner_alt' => true,
            'image_3' => true,
            'image_3_alt' => true,
            'image_4' => true,
            'image_4_alt' => true,
            'order' => true,
            'status' => true,
            'extra_fields' => [],
            'required' => ['title', 'content', 'published_at', 'feature_image', 'feature_image_alt'],
        ],
    ],
    'careers' => [
        'section' => [
            'title' => true,
            'description' => true,
            'banner' => true,
            'banner_alt' => true,
            'filter_enabled' => true,
            'filters' => true,
            'filterable_columns' => ['job_type', 'department', 'location', 'country', 'base'],
            'extra_fields' => [],
            'required' => ['title'],
        ],
        'items' => [
            'columns' => [
                'title' => true,
                'job_type' => true,
                'department' => true,
                'location' => true,
                'published_date' => true,
                'order' => true,
                'status' => true,
            ],
            'title' => true,
            'slug' => true,
            'short_description' => true,
            'job_type' => true,
            'job_type_options' => [
                'full_time' => [
                    'en' => 'Full Time',
                ],
                'part_time' => [
                    'en' => 'Part Time',
                ],
                'remote' => [
                    'en' => 'Remote',
                ],
            ],
            'department' => true,
            'location' => true,
            'country' => true,
            'base' => true,
            'base_options' => [
                'temporary' => [
                    'en' => 'Temporary',
                ],
                'permanent' => [
                    'en' => 'Permanent',
                ],
                'contract' => [
                    'en' => 'Contract',
                ],
            ],
            'about' => true,
            'responsibilities' => true,
            'requirements' => true,
            'join_the_team' => true,
            'published_date' => true,
            'order' => true,
            'status' => true,
            'extra_fields' => [],
            'required' => ['title', 'job_type', 'department', 'location', 'published_date'],
        ],
        'departments' => [
            'columns' => [
                'title' => true,
                'description' => true,
                'order' => true,
                'status' => true,
            ],
            'title' => true,
            'description' => true,
            'stats' => false,
            'order' => true,
            'status' => true,
            'extra_fields' => [],
            'required' => ['title'],
        ],
        'candidates' => [
            'columns' => [
                'name' => true,
                'email' => true,
                'phone' => true,
                'state' => true,
                'country' => true,
                'apply_for' => true,
                'experience' => true,
                'designation' => true,
                'additional_information' => true,
                'attachment' => true,
                'privacy' => true,
                'submitted_at' => true,
            ],
            'name' => true,
            'email' => true,
            'phone' => true,
            'state' => true,
            'country' => true,
            'apply_for' => true,
            'experience' => true,
            'designation' => true,
            'submitted' => true,
            'additional_information' => true,
            'attachment' => true,
            'privacy' => true,
            'extra_fields' => [],
        ],
    ],
    'faqs' => [
        'section' => [
            'title' => true,
            'description' => true,
            'status' => true,
            'extra_fields' => [],
            'required' => ['title'],
        ],
        'items' => [
            'question' => true,
            'answer' => true,
            'order' => true,
            'status' => true,
            'required' => ['question', 'answer'],
        ],
    ],
];

