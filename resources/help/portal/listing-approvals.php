<?php

return [
    'title' => 'Listing Permits',
    'icon' => 'fa-file-shield',
    'intro' => 'Every listing\'s advertising permit in one place, for Super Admin. Approve listings that are waiting for you and take down any listing that should not be on the website.',
    'features' => [
        ['icon' => 'fa-layer-group', 'title' => 'Status tabs', 'text' => 'Listings are grouped as Verified, Not verified (or Awaiting approval), Permit details needed, Permit expired and Taken down, with a count on each.'],
        ['icon' => 'fa-magnifying-glass', 'title' => 'Search', 'text' => 'Find a listing by title, ref, permit number, agency or agent within the current tab.'],
        ['icon' => 'fa-code-compare', 'title' => 'Check the ad against the permit', 'text' => 'Open a listing to see the permit number, expiry, QR code and a side-by-side check of the ad against the permit, with differences flagged.'],
        ['icon' => 'fa-list-check', 'title' => 'Checklist and history', 'text' => 'The checklist shows anything still missing, and Review history lists every status change and who made it.'],
        ['icon' => 'fa-circle-check', 'title' => 'Approve & publish', 'text' => 'For a listing waiting for you with nothing missing, Approve & publish puts it live on the website and tells the agency or agent.'],
        ['icon' => 'fa-ban', 'title' => 'Take down', 'text' => 'Take any listing off the website, for example for wrong details or a DLD complaint. The agency or agent is told by email and in the portal.'],
    ],
    'steps' => [
        ['title' => 'Open the waiting tab', 'text' => 'Go to Not verified / Awaiting approval to see listings that are not on the website yet.'],
        ['title' => 'Open a listing', 'text' => 'Click its title to see the permit details, checklist and the ad-versus-permit comparison.'],
        ['title' => 'Verify the permit', 'text' => 'If it was not verified online, check it with Verify a permit on DLD or the QR code.'],
        ['title' => 'Approve or take down', 'text' => 'Click Approve & publish if everything matches, or Take down if something is wrong.'],
    ],
    'tips' => [
        'DTCM and None (DIFC / JAFZA) listings cannot be validated online, so they always wait for your approval. RERA and ADREC listings can go live by themselves once the agent validates the permit, unless approval for all listings is switched on.',
        'A taken-down listing stays offline until the agency or agent changes its permit details and validates again.',
        'Listings with an expired permit go offline automatically and return once a renewed permit is validated.',
    ],
];
