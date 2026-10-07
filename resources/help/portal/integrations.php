<?php

return [
    'title' => 'Integrations',
    'icon' => 'fa-plug',
    'intro' => 'Connect the tools you already use so leads and listings flow into MW Realty automatically. Each integration has its own card here with its current status.',
    'features' => [
        ['icon' => 'fa-bullhorn', 'title' => 'Facebook Lead Ads', 'text' => 'Leads from your Facebook and Instagram lead forms arrive in Leads automatically.'],
        ['icon' => 'fa-house-chimney', 'title' => 'Property Finder', 'text' => 'Import your Property Finder listings as properties: all of them once, then just the new ones.'],
        ['icon' => 'fa-circle-check', 'title' => 'Status at a glance', 'text' => 'Each card shows whether it is connected, not connected, syncing, or how many Pages are linked.'],
        ['icon' => 'fa-arrow-right', 'title' => 'One page per integration', 'text' => 'Click a card to open its page, where you connect, sync or disconnect it.'],
    ],
    'steps' => [
        ['title' => 'Pick an integration', 'text' => 'Find the card for the tool you use and check its status.'],
        ['title' => 'Open it', 'text' => 'Click Connect (or Open if it is already set up) to go to its page.'],
        ['title' => 'Follow the steps there', 'text' => 'Each page explains how to connect and what is brought in. Come back here any time to see the status of all your integrations.'],
    ],
    'tips' => [
        'Integrations unlock once your account is approved (KYC).',
        'More integrations will appear here as they are added.',
        'For Super Admin, the cards show totals across all accounts, such as Property Finder listings waiting for review.',
    ],
];
