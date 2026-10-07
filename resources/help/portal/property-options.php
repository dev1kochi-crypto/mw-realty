<?php

return [
    'title' => 'Property Options',
    'icon' => 'fa-sliders',
    'intro' => 'Property Options are the choices on the property form, such as Property Type, Listing Type, Furnishing and Amenities. Only Super Admin manages them, and they apply to every agent and agency. Agents and agencies ask for new options with a support ticket.',
    'features' => [
        ['icon' => 'fa-list', 'title' => 'All option lists in one place', 'text' => 'Pick a list on the left: listing form dropdowns (Property Type, Listing Type, Completion Status, Furnishing, Emirate, Rental Period), features with icons (Amenities, Easy Access, Attributes) and Nearby Place Type.'],
        ['icon' => 'fa-plus', 'title' => 'Add and edit options', 'text' => 'Click Add option and enter the name in each active language. The first language is required. The Code is made from the name if you leave it empty.'],
        ['icon' => 'fa-image', 'title' => 'Icons for features', 'text' => 'Amenities, Easy Access and Attributes options can have an icon (SVG or PNG, max 1 MB). It is shown on the property page.'],
        ['icon' => 'fa-grip-lines', 'title' => 'Set the order', 'text' => 'Drag rows to set the order the options appear in on the form.'],
        ['icon' => 'fa-toggle-on', 'title' => 'Switch options on or off', 'text' => 'Use the Shown switch to hide an option from new listings. Listings that already use it keep it.'],
        ['icon' => 'fa-chart-simple', 'title' => 'See how often each is used', 'text' => 'Each row shows how many listings use the option (or how many places, for Nearby Place Type). Long lists also have a search box.'],
    ],
    'steps' => [
        ['title' => 'Choose a list', 'text' => 'Click the list you want to change in the menu on the left.'],
        ['title' => 'Add an option', 'text' => 'Click Add option, fill in the name for each language, add an icon if the list uses one, and click Save.'],
        ['title' => 'Order and tidy up', 'text' => 'Drag rows into the right order, and use the pencil to rename an option.'],
        ['title' => 'Retire old options', 'text' => 'Switch off an option that should no longer be picked. Delete it only if no listing uses it.'],
    ],
    'tips' => [
        'An option used by listings cannot be deleted. Switch it off instead.',
        'Once listings use an option, its Code is locked. You can still rename its labels.',
        'Property Type, Listing Type and Completion Status also appear in the website search, so changes there are visible to visitors.',
    ],
];
