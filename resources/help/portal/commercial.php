<?php

return [
    'title' => 'Commercial',
    'icon' => 'fa-store',
    'intro' => 'Your commercial listings, such as offices, shops and warehouses. They work exactly like Properties but are kept separate and appear on the Commercial page of the website.',
    'features' => [
        ['icon' => 'fa-plus', 'title' => 'Add commercial listings', 'text' => 'Click Add Commercial Property to open the same form as Properties, with the same tabs and permit check.'],
        ['icon' => 'fa-magnifying-glass', 'title' => 'Search and filter', 'text' => 'Search by title, ref no, RERA, address, community or city, and filter by Buy or Rent, Active or Inactive, Premium only or agent.'],
        ['icon' => 'fa-file-shield', 'title' => 'Permit status', 'text' => 'The Permit pills and each card show whether the permit is verified or what is still needed before the listing can go live.'],
        ['icon' => 'fa-chart-line', 'title' => 'Listing Performance', 'text' => 'See impressions, clicks, the quality score and the leads for each listing.'],
        ['icon' => 'fa-star', 'title' => 'Premium and sold', 'text' => 'Make an active listing premium for set dates, or mark it as sold or rented when the deal is done.'],
        ['icon' => 'fa-sort', 'title' => 'Own display order', 'text' => 'Drag cards or use Move to. Commercial listings have their own order, separate from Properties.'],
    ],
    'steps' => [
        ['title' => 'Add a commercial property', 'text' => 'Click Add Commercial Property and fill in the form tabs.'],
        ['title' => 'Validate the permit', 'text' => 'In Core details, enter the advertising permit and click Validate.'],
        ['title' => 'Save and activate', 'text' => 'Save the listing. Once the permit is verified, switch it to Active so it shows on the website.'],
        ['title' => 'Manage it over time', 'text' => 'Edit, reorder, make premium or mark as sold or rented from the card.'],
    ],
    'tips' => [
        'Commercial listings count toward the same plan limit as your Properties.',
        'If you open a commercial listing from a Properties link, you are taken to the Commercial page automatically.',
    ],
];
