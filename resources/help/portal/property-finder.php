<?php

return [
    'title' => 'Property Finder',
    'icon' => 'fa-house-chimney',
    'intro' => 'Bring your Property Finder listings into MW Realty as properties: all of them once, then just the new ones. MW Realty reviews each imported listing before it can go on the website.',
    'features' => [
        ['icon' => 'fa-key', 'title' => 'Connect with your API keys', 'text' => 'Paste your Property Finder API key and API secret and click Verify & connect. They are checked with Property Finder before saving and stored encrypted.'],
        ['icon' => 'fa-cloud-arrow-down', 'title' => 'Import your listings', 'text' => 'Title, description, price, beds, baths, size, location and map pin, photos (watermarked), amenities and permit number are brought in.'],
        ['icon' => 'fa-rotate', 'title' => 'Sync new listings', 'text' => 'After the first import, Sync new listings brings in only what you added on Property Finder since the last sync. Re-check all goes through every live listing again.'],
        ['icon' => 'fa-clone', 'title' => 'No duplicates', 'text' => 'Each listing is imported once. Drafts are skipped, and so is any listing whose permit you already have here.'],
        ['icon' => 'fa-user-tie', 'title' => 'Assigned to the right agent', 'text' => 'For an agency, each listing goes to the agent with the same email or name as the Property Finder agent. If none matches, no agent is set and the agency can assign one.'],
        ['icon' => 'fa-chart-simple', 'title' => 'Live progress', 'text' => 'The page shows how many listings were imported, already here or failed, while the import runs in the background.'],
    ],
    'steps' => [
        ['title' => 'Connect', 'text' => 'Paste your API key and secret (find them in PF Expert under API settings, or ask your Property Finder account manager) and click Verify & connect. Nothing is imported yet.'],
        ['title' => 'Import', 'text' => 'Click Import all listings when you are ready. It runs in the background, so you can leave the page.'],
        ['title' => 'Review', 'text' => 'Imported listings stay off the website until MW Realty reviews them. You are notified when they are approved or rejected.'],
        ['title' => 'Live', 'text' => 'Approved listings go live if their permit details are complete. Otherwise, complete the permit in the property form to publish them.'],
        ['title' => 'Keep it up to date', 'text' => 'Click Sync new listings whenever you add listings on Property Finder.'],
    ],
    'tips' => [
        'Imports count toward your plan\'s listing limit. If the limit is reached, the sync stops; upgrade your plan and sync again to import the rest.',
        'Agents in an agency see the agency\'s connection and can sync it, but only the agency can update the API keys or disconnect.',
        'A rejected listing is removed and will not be imported again. Disconnecting keeps the listings you already imported.',
        'Super Admin sees connected accounts and reviews imports under Review imported listings: tick listings, then Approve or Reject (with an optional reason).',
    ],
];
