<?php

return [
    'title' => 'Marketing Properties',
    'icon' => 'fa-bullhorn',
    'intro' => 'Choose which listings appear in the Realty Property section on the home page. You can pick listings from any agency or agent and set the order they show in.',
    'features' => [
        ['icon' => 'fa-users', 'title' => 'Pick agencies and agents', 'text' => 'Search and tick one or more agencies or agents to see their active listings.'],
        ['icon' => 'fa-check-square', 'title' => 'Add several at once', 'text' => 'Tick the listings you want and click Add selected. New ones go to the top of the list.'],
        ['icon' => 'fa-grip-vertical', 'title' => 'Set the order', 'text' => 'Drag rows, or use the up and down arrows, to change the order. The new order is saved straight away.'],
        ['icon' => 'fa-house', 'title' => 'Home page and View more', 'text' => 'The first 12 listings show on the home page. The rest show only on the View more page. A line in the list marks where the home page stops.'],
        ['icon' => 'fa-trash', 'title' => 'Remove a listing', 'text' => 'Remove a listing from the list when you no longer want to promote it. The listing itself is not changed.'],
        ['icon' => 'fa-arrow-up-right-from-square', 'title' => 'View on website', 'text' => 'Open the public page to check how the list looks to visitors.'],
    ],
    'steps' => [
        ['title' => 'Choose accounts', 'text' => 'Under Agencies & agents, tick the accounts whose listings you want to promote.'],
        ['title' => 'Select listings', 'text' => 'Under Their properties, search by title or reference and tick the listings. Ones already on the list are marked On list.'],
        ['title' => 'Add them', 'text' => 'Click Add selected. They appear at the top of the Marketing list.'],
        ['title' => 'Arrange the list', 'text' => 'Drag the most important listings into the top 12 so they show on the home page.'],
    ],
    'tips' => [
        'This page is for Super Admin only.',
        'Until you add any listings, the home page shows the latest listings automatically.',
        'Only active listings show on the website. If a listing on the list is switched off, it is skipped.',
    ],
];
