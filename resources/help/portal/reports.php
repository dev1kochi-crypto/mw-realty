<?php

return [
    'title' => 'Reports',
    'icon' => 'fa-chart-pie',
    'intro' => 'Charts and figures about your leads, listings and closed deals. Use them to see what is working and where to focus.',
    'features' => [
        ['icon' => 'fa-calendar', 'title' => 'Date range', 'text' => 'Switch between 7 Days, 30 Days, 90 Days and 12 Months at the top right. Every report updates to that period.'],
        ['icon' => 'fa-address-book', 'title' => 'Leads report', 'text' => 'Total Leads, Closed Leads and Conversion Rate, plus Leads Over Time, Leads by Source, Leads by Stage and your Most Enquired Properties.'],
        ['icon' => 'fa-building', 'title' => 'Properties report', 'text' => 'Listings Added Over Time, Sale vs Rent, Residential vs Commercial, By Property Type, and your Top Properties by Leads.'],
        ['icon' => 'fa-handshake', 'title' => 'Sales report', 'text' => 'Total Sales, Deals Closed, Average Deal, Win Rate, Lost Deals and Open Pipeline, with charts by agent, property type and location, and a list of All Deals.'],
        ['icon' => 'fa-file-csv', 'title' => 'Export deals', 'text' => 'On the Sales report, filter All Deals by outcome (Won, Lost or both), sale or rent, or a search, then click Export CSV to download them.'],
        ['icon' => 'fa-user-tie', 'title' => 'Agents report (agencies)', 'text' => 'Agencies see Agent Performance: each agent\'s properties, leads, closed deals, conversion and last lead.'],
    ],
    'steps' => [
        ['title' => 'Choose a report', 'text' => 'Click the Leads, Properties, Sales or Agents tab.'],
        ['title' => 'Set the period', 'text' => 'Pick a date range to compare recent activity with the longer trend.'],
        ['title' => 'Read the results', 'text' => 'Check the totals first, then use the charts to see which sources, listings or agents perform best.'],
        ['title' => 'Export if needed', 'text' => 'On the Sales report, filter the deals list and click Export CSV to share or keep the figures.'],
    ],
    'tips' => [
        'Reports are part of your plan. If the page shows Unlock reports & analytics, click View Plans to upgrade. Agents on an agency plan need the agency to upgrade.',
        'A deal counts in the Sales report when you move its lead to a won stage, such as Closed Won. Lost stages count as lost deals.',
        'A deal\'s value is the sold or rent price recorded when the listing was marked as sold to that lead, otherwise the listing price.',
    ],
];
