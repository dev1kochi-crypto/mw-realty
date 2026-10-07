<?php

return [
    'title' => 'Facebook Lead Ads',
    'icon' => 'fa-bullhorn',
    'intro' => 'Connect your Facebook Pages so every lead from your Facebook and Instagram lead forms lands in Leads automatically. The ad set or ad name becomes the lead\'s Source, so you can see which campaign brought each lead.',
    'features' => [
        ['icon' => 'fa-link', 'title' => 'Connect your Pages', 'text' => 'Log in with Facebook in a secure pop-up window and tick the Pages that run your lead ads.'],
        ['icon' => 'fa-bolt', 'title' => 'Leads arrive by themselves', 'text' => 'Each form submission becomes a lead within seconds, with name, email, phone and the form answers.'],
        ['icon' => 'fa-clock-rotate-left', 'title' => 'Import older leads', 'text' => 'When connecting, you can also import the leads your Pages already have: all of them, or the last 90, 30 or 7 days. This runs in the background.'],
        ['icon' => 'fa-rotate', 'title' => 'Sync now or All leads', 'text' => 'Sync now fetches leads since the last sync. All leads fetches every lead on the Page in the background, and you get one summary email at the end.'],
        ['icon' => 'fa-clone', 'title' => 'No duplicates', 'text' => 'People already in your CRM are not added twice. A lead from a new ad is added to their history instead.'],
        ['icon' => 'fa-heart-pulse', 'title' => 'Page status', 'text' => 'The Connected Pages table shows if each Page is Receiving leads or needs attention, plus its lead count and last lead.'],
        ['icon' => 'fa-link-slash', 'title' => 'Disconnect', 'text' => 'Disconnect one Page, or tick several and use Disconnect selected. Leads already in the CRM stay.'],
    ],
    'steps' => [
        ['title' => 'Get ready', 'text' => 'Use a Facebook account with full control (admin) of the Page, make sure the Page has a lead form, and allow pop-ups for this site.'],
        ['title' => 'Continue with Facebook', 'text' => 'Click Continue with Facebook (or Connect another Page) and log in. If Facebook asks to continue with previous settings, click Edit settings.'],
        ['title' => 'Allow access', 'text' => 'Select all the Pages you want and keep every permission switched on.'],
        ['title' => 'Choose your Pages', 'text' => 'Back here, tick the Pages, choose whether to import their existing leads, and click Connect Pages.'],
        ['title' => 'Work your leads', 'text' => 'New leads now arrive in Leads. Use Sync now any time to fetch recent ones.'],
    ],
    'tips' => [
        'If a Page shows Reconnect needed, click Reconnect and log in again. Leads missed in the meantime are fetched automatically.',
        'A Page can feed one MW Realty account only. If it is connected to another account, it must be disconnected there first.',
        'If you are an agent in an agency, Pages you connect are your own: their leads come to you as personal leads, not to your agency.',
        'Super Admin can connect Pages and assign each one to an agency or independent agent, whose CRM then receives its leads.',
    ],
];
