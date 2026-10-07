<?php

return [
    'title' => 'Lead Insights',
    'icon' => 'fa-chart-line',
    'intro' => 'See how your website leads behave on the site: which listings they view, how long they stay, what they search for and their AI chats. Use it to spot your most interested buyers.',
    'features' => [
        ['icon' => 'fa-gauge-high', 'title' => 'Summary cards', 'text' => 'The top cards show Leads with website activity, Property views, Total time on site, AI chats and how many were Active this week.'],
        ['icon' => 'fa-sort', 'title' => 'Sort by interest', 'text' => 'Use Sort to rank leads by Recently active, Most time on site, Most property views or Most AI chats.'],
        ['icon' => 'fa-magnifying-glass', 'title' => 'Search', 'text' => 'Find a lead by name, email or phone.'],
        ['icon' => 'fa-table-list', 'title' => 'Activity at a glance', 'text' => 'Each row shows the lead\'s stage, property views, searches, time on site, AI chats and when they were last active.'],
        ['icon' => 'fa-chart-line', 'title' => 'Full activity', 'text' => 'Click a lead or Insights to open the lead on its Insights tab, with their full activity timeline, favorites, searches and AI chats.'],
    ],
    'steps' => [
        ['title' => 'Pick a sort order', 'text' => 'Start with Recently active to see who is browsing right now, or Most property views to find the keenest buyers.'],
        ['title' => 'Open a lead', 'text' => 'Click Insights on a row to see exactly which listings they looked at and what they asked in AI chat.'],
        ['title' => 'Follow up', 'text' => 'Use what you learned to call or message the lead with listings that match their interest.'],
    ],
    'tips' => [
        'Only leads that came from the website (AI chat, enquiry forms or customer accounts) appear here. Leads you added yourself or imported have no website activity.',
        'A lead who was active this week is a good one to contact today.',
        'The Website Insights card on the Leads page filters the leads table to these same leads.',
    ],
];
