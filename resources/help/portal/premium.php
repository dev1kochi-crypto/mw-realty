<?php

return [
    'title' => 'Premium',
    'icon' => 'fa-star',
    'intro' => 'Premium listings get a Premium badge and appear in the Premium section on the website. This page shows all your live and scheduled premium listings from Properties and Commercial in one place.',
    'features' => [
        ['icon' => 'fa-list', 'title' => 'Live and scheduled in one list', 'text' => 'Use the All, Live and Scheduled tabs to see what is premium right now and what starts later. Each row shows the period and how many days are left.'],
        ['icon' => 'fa-plus', 'title' => 'Add Premium', 'text' => 'Search your active listings by title, reference or location, tick one or more, and book them together for the same dates.'],
        ['icon' => 'fa-calendar-days', 'title' => 'Pick the period', 'text' => 'Choose a start and end date, or use the quick 7, 15, 30, 60 or 90 day buttons. A start date in the future schedules it; it goes live by itself on that day.'],
        ['icon' => 'fa-edit', 'title' => 'Change the dates', 'text' => 'Use the edit button on a row to move the dates. Once a listing is live, only the end date can change.'],
        ['icon' => 'fa-ban', 'title' => 'Stop or cancel', 'text' => 'Stop removes the Premium badge right away. Cancel removes a scheduled booking before it starts.'],
        ['icon' => 'fa-layer-group', 'title' => 'See your plan allowance', 'text' => 'The chips show how many premium listings you are using, the longest period allowed, and (on monthly plans) when your allowance resets.'],
    ],
    'steps' => [
        ['title' => 'Click Add Premium', 'text' => 'The button appears when your plan includes premium listings.'],
        ['title' => 'Choose listings', 'text' => 'Search and tick the listings you want. Only active listings that are not already premium or scheduled can be picked.'],
        ['title' => 'Set the dates', 'text' => 'Pick a start and end date. Start today to go live now, or pick a later date to schedule it.'],
        ['title' => 'Confirm', 'text' => 'If any listing does not fit your plan, nothing is booked and you see which one caused the problem.'],
        ['title' => 'Manage it here', 'text' => 'Come back to this page to edit dates, stop a live premium or cancel a scheduled one.'],
    ],
    'tips' => [
        'You can also make a single listing premium with the Make premium button on its card in Properties.',
        'On a plan that counts premium listings per month, stopping one early does not give it back. Cancelling a scheduled one before it starts does.',
        'If you work in an agency, you share the agency\'s premium allowance with the other agents on its plan.',
        'Premium dates set by MW Realty are locked. Contact MW Realty if they need to change.',
    ],
];
