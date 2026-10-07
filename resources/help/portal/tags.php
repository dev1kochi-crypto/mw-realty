<?php

return [
    'title' => 'Tag',
    'icon' => 'fa-tag',
    'intro' => 'Tags are coloured labels you put on leads, such as Hot Lead or VIP. They help you group leads and spot patterns at a glance.',
    'features' => [
        ['icon' => 'fa-plus', 'title' => 'Create tags', 'text' => 'Click Add Tag, type a name and pick a colour. A preview shows how the tag will look.'],
        ['icon' => 'fa-tags', 'title' => 'More than one per lead', 'text' => 'A lead can carry several tags at the same time, so you can combine labels like VIP and Investor.'],
        ['icon' => 'fa-globe', 'title' => 'MW Realty tags', 'text' => 'Tags marked MW Realty are set for every account. You can use them, but you cannot edit or delete them.'],
        ['icon' => 'fa-pen', 'title' => 'Edit your tags', 'text' => 'Tags you add are yours only. Use Edit to change the name or colour at any time.'],
        ['icon' => 'fa-users', 'title' => 'See tagged leads', 'text' => 'The Leads column shows how many of your leads have each tag. Click the number to see them and remove the tag from some or all of them.'],
    ],
    'steps' => [
        ['title' => 'Add a tag', 'text' => 'Click Add Tag, enter a name, choose a colour and click Add Tag.'],
        ['title' => 'Use it on your leads', 'text' => 'Open the Leads page and add the tag to the leads it fits.'],
        ['title' => 'Keep the list tidy', 'text' => 'Rename tags with Edit, or remove ones you no longer use with Delete.'],
    ],
    'tips' => [
        'A tag still used by leads cannot be deleted. Click its lead count, remove it from those leads, then delete it.',
        'Each tag name can be used only once.',
        'Pick clearly different colours so tags are easy to tell apart in the leads list.',
    ],
];
