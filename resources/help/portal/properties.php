<?php

return [
    'title' => 'Properties',
    'icon' => 'fa-building',
    'intro' => 'All your residential listings in one place. Add new listings, see which ones are live, fix permit problems and follow how each listing performs.',
    'features' => [
        ['icon' => 'fa-magnifying-glass', 'title' => 'Search and filter', 'text' => 'Search by title, ref no, RERA, address, community or city. Filter by Buy or Rent, Active or Inactive, Premium only, and (for agencies) by agent.'],
        ['icon' => 'fa-file-shield', 'title' => 'Permit status pills', 'text' => 'The Permit pills show how many listings are Verified, Not verified, Permit details needed, Permit expired or Taken down. Click a pill to see only those listings.'],
        ['icon' => 'fa-toggle-on', 'title' => 'Switch listings on and off', 'text' => 'Use the switch on each card, or tick several cards and use Bulk Actions to Mark Active, Mark Inactive or Delete Selected. A listing can only go live once its permit is verified.'],
        ['icon' => 'fa-chart-line', 'title' => 'Listing Performance', 'text' => 'Click Leads or the chart button on a card to see impressions, clicks, the quality score with tips to improve it, and the leads for that listing.'],
        ['icon' => 'fa-star', 'title' => 'Make premium', 'text' => 'Book start and end dates to show an active listing in the Premium section of the website. How many you can run depends on your plan.'],
        ['icon' => 'fa-handshake', 'title' => 'Mark as sold or rented', 'text' => 'Record the price, date, buyer and the deal documents. The listing leaves the website and moves to Sold Listings, and you can undo this later.'],
        ['icon' => 'fa-sort', 'title' => 'Set the display order', 'text' => 'Drag cards by their handle to reorder the page, or use Move to for Top, Bottom or any position.'],
        ['icon' => 'fa-grip', 'title' => 'Grid or List view', 'text' => 'Switch between cards and a table with the Grid and List buttons. Your choice is remembered while you are logged in.'],
    ],
    'steps' => [
        ['title' => 'Add a property', 'text' => 'Click Add Property and fill in the form. The plan chip at the top shows how many listings you have used.'],
        ['title' => 'Validate the permit', 'text' => 'In Core details, enter the advertising permit and click Validate. The listing stays off the website until the permit is verified.'],
        ['title' => 'Check the card', 'text' => 'Each card shows its permit status. If it says something is needed, click it to open the permit details and fix it.'],
        ['title' => 'Go live', 'text' => 'Once the permit is verified, switch the listing to Active so it shows on the website.'],
        ['title' => 'Track and close', 'text' => 'Watch leads and the quality score in Listing Performance, then mark the listing as sold or rented when the deal is done.'],
    ],
    'tips' => [
        'The red badge next to Properties in the menu counts listings that were taken down or whose permit expired. These need your action.',
        'Your plan limit counts Properties and Commercial listings together.',
        'Agents in an agency can view and edit the agency listings assigned to them. Deleting, reordering, switching on or off and making premium are done by the listing owner.',
        'Drag to reorder is turned off while you search or filter. Use Move to instead.',
    ],
];
