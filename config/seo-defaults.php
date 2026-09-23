<?php

// Branded fallback copy for each static page key (matches config/cms/pages.php's
// default_pages keys). Used by SeoMeta::resolve() whenever that page's admin-entered
// Metadata row has no meta_title/meta_description set for the current language.
return [
    'home' => [
        'meta_title' => 'MW Realty | Buy, Rent & Invest in UAE Real Estate',
        'meta_description' => 'Discover verified apartments, villas, and commercial properties for sale and rent across the UAE with MW Realty — Dubai\'s trusted real estate partner.',
    ],
    'about' => [
        'meta_title' => 'About Us | MW Realty',
        'meta_description' => 'Learn about MW Realty — Dubai\'s trusted real estate partner helping buyers, tenants, and investors find the right property across the UAE.',
    ],
    'agencies' => [
        'meta_title' => 'Real Estate Agencies in the UAE | MW Realty',
        'meta_description' => 'Browse verified real estate agencies across the UAE on MW Realty and connect with trusted teams for buying, renting, or investing.',
    ],
    'agents' => [
        'meta_title' => 'Real Estate Agents in the UAE | MW Realty',
        'meta_description' => 'Find experienced, verified real estate agents across the UAE on MW Realty to help you buy, rent, or sell with confidence.',
    ],
    'blog' => [
        'meta_title' => 'Real Estate Blog | MW Realty',
        'meta_description' => 'Insights, guides, and market trends for buyers, tenants, and investors navigating the UAE real estate market.',
    ],
    'properties' => [
        'meta_title' => 'Properties for Sale & Rent in the UAE | MW Realty',
        'meta_description' => 'Browse verified residential listings across Dubai and the UAE. Find your next home or investment with MW Realty.',
    ],
    'commercial' => [
        'meta_title' => 'Commercial Properties for Sale & Rent | MW Realty',
        'meta_description' => 'Explore verified commercial properties — offices, retail, and warehouses — for sale and rent across the UAE with MW Realty.',
    ],
    'residential' => [
        'meta_title' => 'Residential Properties in the UAE | MW Realty',
        'meta_description' => 'Explore apartments, villas, and townhouses for sale and rent across the UAE with MW Realty.',
    ],
    'developments' => [
        'meta_title' => 'New Developments & Off-Plan Projects | MW Realty',
        'meta_description' => 'Discover new developments and off-plan projects across the UAE, from Dubai\'s leading developers, with MW Realty.',
    ],
    'contact' => [
        'meta_title' => 'Contact Us | MW Realty',
        'meta_description' => 'Get in touch with MW Realty — reach our team for property enquiries, viewings, or general questions about the UAE real estate market.',
    ],
    'terms' => [
        'meta_title' => 'Terms & Conditions | MW Realty',
        'meta_description' => 'Read the terms and conditions governing the use of MW Realty\'s website and services.',
    ],
    'privacy' => [
        'meta_title' => 'Privacy Policy | MW Realty',
        'meta_description' => 'Read MW Realty\'s privacy policy to understand how we collect, use, and protect your personal information.',
    ],
    'security' => [
        'meta_title' => 'Security Policy | MW Realty',
        'meta_description' => 'Read MW Realty\'s security policy covering how we protect your data and keep our platform safe.',
    ],
    'cookie' => [
        'meta_title' => 'Cookie Policy | MW Realty',
        'meta_description' => 'Read MW Realty\'s cookie policy to understand how cookies are used across our website and how to manage your preferences.',
    ],
];
