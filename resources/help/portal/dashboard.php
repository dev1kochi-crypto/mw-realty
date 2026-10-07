<?php

return [
    'title' => 'Dashboard',
    'icon' => 'fa-chart-pie',
    'intro' => 'Your home screen. It shows how your enquiries and listings are doing at a glance, and points you to anything that needs attention today.',
    'features' => [
        ['icon' => 'fa-bell', 'title' => 'Alerts at the top', 'text' => 'Coloured chips show things to act on, such as leads waiting 2+ days, active leads, Premium listings expiring in 7 days, inactive listings or a full listing limit. Click a chip to jump straight there.'],
        ['icon' => 'fa-gauge-high', 'title' => 'Key numbers', 'text' => 'Six cards show Total enquiries, Deals in progress, Deals won, Need follow-up, Listings and Premium. The small percentage compares the last 30 days with the 30 days before.'],
        ['icon' => 'fa-chart-line', 'title' => 'Performance and conversion', 'text' => 'Performance charts weekly enquiries and new listings over the last 12 weeks. Conversion shows your win rate on closed deals (won versus lost).'],
        ['icon' => 'fa-users', 'title' => 'Buyer Insights', 'text' => 'See which day of the week enquiries arrive most (last 90 days) and which sources they come from.'],
        ['icon' => 'fa-layer-group', 'title' => 'Deal Pipeline', 'text' => 'A bar of your enquiries split by stage, so you can see where leads are sitting in your sales process.'],
        ['icon' => 'fa-building', 'title' => 'Listings and enquiries', 'text' => 'Latest Enquiries lists your newest leads. Most Desired Properties ranks listings by buyer enquiries, and Inventory breaks your listings down by sale or rent and property type.'],
        ['icon' => 'fa-id-card', 'title' => 'Plan card', 'text' => 'The card on the right shows your plan, how many listings you have used, whether Reports are on, and (for agencies) your agent count. Click MANAGE or UPGRADE to open Plans.'],
        ['icon' => 'fa-bolt', 'title' => 'Shortcuts', 'text' => 'The row of buttons under the greeting takes you straight to common pages like Add Property, Leads, Import Leads and Reports.'],
    ],
    'steps' => [
        ['title' => 'Check the alerts', 'text' => 'Start your day with the chips at the top. If you see Everything is up to date, nothing is waiting for you.'],
        ['title' => 'Follow up on waiting leads', 'text' => 'Click Need follow-up or the leads-waiting chip. These are leads still in the first stage after 2 days.'],
        ['title' => 'Review your numbers', 'text' => 'Look at the key number cards and charts to see whether enquiries and listings are going up or down.'],
        ['title' => 'Jump to work', 'text' => 'Click any card, listing or shortcut to open that page and take action.'],
    ],
    'tips' => [
        'Most cards and chips are clickable and open the matching page.',
        'A lead counts as Need follow-up when it is still in your default stage (for example New) 2 days after it arrived. Move it to another stage to clear it.',
        'Shortcuts to Leads, Properties and Reports appear once your KYC is approved.',
        'Agents working on their agency\'s plan see the agency\'s plan and listing usage on the plan card.',
    ],
];
