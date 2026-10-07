<?php

return [
    'title' => 'My Agency',
    'icon' => 'fa-building-user',
    'intro' => 'See which agency you work with, answer agency invitations, ask to join an agency, or leave one. Your own account, personal listings and history always stay yours.',
    'features' => [
        ['icon' => 'fa-envelope-open-text', 'title' => 'Answer invitations', 'text' => 'When an agency invites you, the invitation shows at the top of the page. Click Accept or Decline. The number on My Agency in the sidebar shows how many invitations are waiting.'],
        ['icon' => 'fa-magnifying-glass', 'title' => 'Request to join an agency', 'text' => 'If you are an independent agent, search for an agency in the picker and click Send request. You can only have one join request in progress at a time.'],
        ['icon' => 'fa-building', 'title' => 'Current agency', 'text' => 'See the agency you belong to, your status, the date you joined, and which plan covers you.'],
        ['icon' => 'fa-layer-group', 'title' => 'Your plan when you join', 'text' => 'If the agency\'s plan includes team agents, you move onto the agency\'s plan once approved. A paid plan of your own stops with no refund for the current period, and a card subscription will not renew.'],
        ['icon' => 'fa-right-left', 'title' => 'Transfer personal listings', 'text' => 'While in an agency, you can move one of your personal listings into the agency with Transfer to agency. You stay its agent, and it counts toward the agency\'s plan.'],
        ['icon' => 'fa-clock-rotate-left', 'title' => 'Agency history', 'text' => 'A list of your past and current agency requests and memberships, with joined and left dates.'],
        ['icon' => 'fa-door-open', 'title' => 'Leave agency', 'text' => 'Click Leave agency to become an independent agent again. The agency keeps its own listings and leads.'],
    ],
    'steps' => [
        ['title' => 'Get your account approved', 'text' => 'You can send a join request or accept an invitation only after your own account (KYC) is approved.'],
        ['title' => 'Send a request or accept an invitation', 'text' => 'Pick an agency and click Send request, or click Accept on an invitation you received.'],
        ['title' => 'Wait for the agency', 'text' => 'A join request must be accepted by the agency first. You can click Withdraw while your request is waiting.'],
        ['title' => 'Wait for Super Admin approval', 'text' => 'After the agency accepts (or you accept their invitation), Super Admin approves the move. You get a notification and an email when it is done.'],
        ['title' => 'Work with the agency', 'text' => 'Once approved, you receive the agency\'s leads and the listings assigned to you. Listings you add from now on belong to the agency.'],
    ],
    'tips' => [
        'You can belong to only one agency at a time. Leave your current agency before joining another one.',
        'Transferring a listing to the agency cannot be undone from your side, and the listing stays with the agency if you leave.',
        'If you leave or are removed, your account and personal listings stay exactly as they are.',
        'The agency can suspend your access. While suspended, no new leads are assigned to you.',
    ],
];
