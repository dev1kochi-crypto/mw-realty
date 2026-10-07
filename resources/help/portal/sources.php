<?php

return [
    'title' => 'Source',
    'icon' => 'fa-share-nodes',
    'intro' => 'Sources show where each lead came from, such as Website, Referral or Instagram. Keeping them tidy helps you see which channels bring you the most leads.',
    'features' => [
        ['icon' => 'fa-plus', 'title' => 'Add your own sources', 'text' => 'Click Add Source and type a name, for example Instagram or Referral. Sources you add are yours only.'],
        ['icon' => 'fa-globe', 'title' => 'MW Realty sources', 'text' => 'Sources marked MW Realty are set for every account. You can use them on your leads, but you cannot change them.'],
        ['icon' => 'fa-grip-lines', 'title' => 'Drag to reorder', 'text' => 'Drag your own rows to set the order sources appear in when you pick one for a lead.'],
        ['icon' => 'fa-wand-magic-sparkles', 'title' => 'Filled in for you', 'text' => 'Leads that arrive on their own, for example from the website or from Facebook Lead Ads, get their source set automatically.'],
        ['icon' => 'fa-users', 'title' => 'See the leads per source', 'text' => 'The Leads column shows how many of your leads use each source. Click the number to see them and remove the source from some or all of them.'],
    ],
    'steps' => [
        ['title' => 'Add a source', 'text' => 'Click Add Source, enter the name and click Add Source.'],
        ['title' => 'Order the list', 'text' => 'Drag your rows so the sources you use most are near the top.'],
        ['title' => 'Use it on your leads', 'text' => 'Choose the source when you add or edit a lead.'],
        ['title' => 'Edit or delete', 'text' => 'Use Edit to rename a source, or Delete to remove one you no longer need.'],
    ],
    'tips' => [
        'A source still used by leads cannot be deleted. Click its lead count, remove it from those leads, then delete it.',
        'For Facebook Lead Ads, the ad set or ad name becomes the lead\'s source, so you can compare campaigns.',
        'Each source name can be used only once.',
    ],
];
