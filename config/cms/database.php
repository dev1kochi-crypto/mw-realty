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
            'sub_heading_2' => false,
            'section_image' => false,
            'section_image_alt' => false,
            'banner' => false,
            'banner_alt' => false,
            'description' => false,
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
        'max_items' => 1, // Dynamic limit for banners
        'allowed_types' => ['video'], // Available banner types
        'items' => [
            'banner_type' => false, // New: image, video toggle
            'line_1' => true,
            'line_2' => true,
            'content' => true,
            'image' => true,
            'video_url' => true, // New: for video banners
            'video_file' => true, // New: for video file uploads
            'image_alt' => false,
            'buttons' => false, // New: JSON array of buttons
            'additional_buttons'=> false, // New: toggle for additional buttons
            'google_review_text' => false, // New
            'google_rating' => false, // New
            'google_review_count' => false, // New
            'google_avatars' => false, // New: for the avatars in screenshot
            'google_avatars_alt' => false, // New: alt text for avatars
            'order' => true,
            'status' => true,
            'extra_fields' => [
                // Small note strip under the search bar, e.g.
                // "Want to find out more about UAE real estate using AI?  Coming Soon  -> Let Us Guide Your Home"
                'note_text' => [
                    'label' => 'Note Text',
                    'type' => 'text',
                    'translatable' => true,
                    'placeholder' => 'e.g. Want to find out more about UAE real estate using AI?',
                    'helpText' => 'Small text shown under the search bar on the home banner.',
                ],
                'note_badge' => [
                    'label' => 'Note Badge',
                    'type' => 'text',
                    'translatable' => true,
                    'placeholder' => 'e.g. Coming Soon',
                ],
                'note_link_text' => [
                    'label' => 'Note Link Text',
                    'type' => 'text',
                    'translatable' => true,
                    'placeholder' => 'e.g. Let Us Guide Your Home',
                ],
                'note_link_url' => [
                    'label' => 'Note Link URL',
                    'type' => 'url',
                    'translatable' => true,
                    'placeholder' => 'https://...',
                ],
            ],
            'required' => ['line_1'], // Mandatory fields
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
        'working_hours' => true,
        'phone_1' => true,
        'phone_2' => true,
        'phone_3' => false,
        'phone_4' => false,
        'whatsapp_number' => true,
        'toll_free' => true,
        'email_1' => true,
        'email_2' => true,
        'email_3' => false,
        'email_4' => false,
        'receipt_email' => true,
        'privacy_policy' => true,
        'terms_and_conditions' => true,
        'disclaimer' => false,
        'logo' => true,
        'logo_alt' => true,
        'footer_logo' => false,
        'footer_logo_alt' => false,
        'footer_description' => false,
        'extra_fields' => [
            'logo_colour' => [
                'label' => 'Colour Logo',
                'type' => 'file',
                'accept' => 'image/*',
                'helpText' => 'Recommended size: 200x60px (PNG/SVG). An alternate colour version of the main logo.',
            ],
            'security_settings' => [
                'label' => 'Security Setting',
                'type' => 'textarea',
                'editor' => 'tinymce',
                'translatable' => true,
            ],
            'cookie_policy' => [
                'label' => 'Cookie Policy',
                'type' => 'textarea',
                'editor' => 'tinymce',
                'translatable' => true,
            ],
        ],
        'facebook' => true,
        'twitter' => true,
        'linkedin' => true,
        'instagram' => true,
        'tiktok' => false,
        'snapchat' => false,
        'pinterest' => false,
        'youtube' => false,
        'skype' => false,
        'whatsapp_social' => true,
        'vimeo' => false,
        'gtag' => true,
        'custom_head_script' => true,
        'custom_body_script' => true,
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
            'required' => ['image'],
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
            'banner_image' => false,
            'banner_alt' => false,
            'image_3' => false,
            'image_3_alt' => false,
            'image_4' => false,
            'image_4_alt' => false,
            'order' => true,
            'status' => true,
            // 'category' is intentionally NOT declared here — it's rendered as its own dynamic
            // <select> (see blogs/create.blade.php and blogs/edit.blade.php) sourced from the
            // admin-managed Blogs > Categories list (BlogCategory model) instead of a fixed set
            // of options, so admins can add/rename/remove categories without a code change.
            'extra_fields' => [
                // No manual "Read Time" field here on purpose — it's calculated from the post's
                // word count at display time (see BlogPageService::calculateReadTime) instead of
                // being typed in by hand, so it can never drift from the actual content length.
                'author_name' => [
                    'type' => 'text',
                    'label' => 'Author Name',
                    'column_class' => 'col-md-4',
                ],
                'author_role' => [
                    'type' => 'text',
                    'label' => 'Author Role',
                    'placeholder' => 'e.g. Senior Investment Consultant',
                    'column_class' => 'col-md-6',
                ],
                'author_avatar' => [
                    'type' => 'file',
                    'label' => 'Author Avatar',
                    'column_class' => 'col-md-6',
                ],
                'excerpt' => [
                    'type' => 'textarea',
                    'label' => 'Excerpt',
                    'translatable' => true,
                    'placeholder' => 'Short summary shown on the blog listing card.',
                    'column_class' => 'col-12',
                ],
            ],
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
    'post-property-steps' => [
        'section' => [
            'title_1' => true, // small eyebrow line shown above Title on the home page
            'title' => true,
            'description' => true,
            'button_text' => true,
            'button_url' => true,
            'image' => true,
            'status' => true,
            'extra_fields' => [],
            'required' => ['title'],
        ],
        'items' => [
            'image' => true, // step icon
            'title' => true,
            'description' => true,
            'order' => true,
            'status' => true,
            'extra_fields' => [],
            'required' => ['title'],
        ],
    ],
    'popular-places' => [
        'section' => [
            'title_1' => true, // small eyebrow line shown above Title on the home page
            'title' => true,
            'status' => true,
            'extra_fields' => [],
            'required' => ['title'],
        ],
        'items' => [
            'image' => true,
            'name' => true,
            'order' => true,
            'status' => true,
            'extra_fields' => [],
            'required' => ['name', 'image'],
        ],
    ],
    'luxury-projects' => [
        'section' => [
            'title' => true,
            'description' => true,
            'status' => true,
            'extra_fields' => [
                'button_name' => [
                    'label' => 'Button Name',
                    'type' => 'text',
                    'translatable' => true,
                    'placeholder' => 'e.g. View More Details',
                ],
                'button_url' => [
                    'label' => 'Button URL',
                    'type' => 'url',
                    'translatable' => true,
                    'placeholder' => 'https://...',
                ],
            ],
            'required' => ['title'],
        ],
    ],
];

