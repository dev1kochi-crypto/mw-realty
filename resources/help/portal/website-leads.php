<?php

return [
    'title' => 'Website Leads',
    'icon' => 'fa-user-clock',
    'intro' => 'Everyone who shared their details on the website, through AI chat, contact or landing page forms, property enquiries or customer accounts. Use it to send unclaimed leads to the right agency or agent.',
    'features' => [
        ['icon' => 'fa-inbox', 'title' => 'Lead Pool', 'text' => 'Visitors who have not opened a listing that belongs to an agency or agent yet. These are waiting for you to transfer them.'],
        ['icon' => 'fa-share', 'title' => 'Routed Leads', 'text' => 'Leads already handed to an agency or agent, either automatically when they viewed one of its listings or because you transferred them.'],
        ['icon' => 'fa-users', 'title' => 'All Website Leads', 'text' => 'Everyone who identified themselves on the website, with their tracked activity.'],
        ['icon' => 'fa-filter', 'title' => 'Search and filter', 'text' => 'Search by name, email or phone, and filter by Came in via (AI Chat, Contact Form, Landing Page, Property Enquiry or Customer Account).'],
        ['icon' => 'fa-chart-line', 'title' => 'Lead profile and insights', 'text' => 'Open a lead to see their contact details, what they are most interested in, and their property views, time on site, searches, favorites and AI chats.'],
        ['icon' => 'fa-share-from-square', 'title' => 'Transfer', 'text' => 'Send one lead, the ticked leads, or every matching lead to an approved, active agency or agent, with an optional note.'],
    ],
    'steps' => [
        ['title' => 'Open the Lead Pool', 'text' => 'Start on the Lead Pool tab to see visitors no one has claimed yet.'],
        ['title' => 'Review the lead', 'text' => 'Click a lead to check their contact details and website activity, so you know who would suit them best.'],
        ['title' => 'Select leads', 'text' => 'Tick one or more leads. Tick the whole page and you can choose to select all matching leads.'],
        ['title' => 'Transfer', 'text' => 'Click Transfer, search for the agency or agent, add a note if you like, and confirm.'],
    ],
    'tips' => [
        'Each transferred lead becomes a CRM lead of the chosen agency or agent. They are notified, and the chat history and website insights go with it.',
        'Selecting all matching leads transfers up to 500 at a time.',
        'Only approved, active agencies and agents can receive leads.',
        'This page is only available to Super Admin.',
    ],
];
