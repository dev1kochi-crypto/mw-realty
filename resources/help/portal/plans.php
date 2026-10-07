<?php

return [
    'title' => 'Plans',
    'icon' => 'fa-layer-group',
    'intro' => 'See your current plan and how many listings you use, compare plans, and upgrade or change your plan. You can also view your payment history and download invoices.',
    'features' => [
        ['icon' => 'fa-gauge', 'title' => 'Your plan at a glance', 'text' => 'The bar at the top shows your current plan, listings used against your limit, and, for a card subscription, its status and renewal date.'],
        ['icon' => 'fa-table-columns', 'title' => 'Compare plans', 'text' => 'Each plan card shows its price and what is included or not. Use the Monthly and Yearly toggle to see yearly prices and savings, where a plan offers them.'],
        ['icon' => 'fa-ticket', 'title' => 'Coupon codes', 'text' => 'Type a code in Coupon code and click Apply to see discounted prices on each plan before you choose.'],
        ['icon' => 'fa-credit-card', 'title' => 'Subscribe & Pay', 'text' => 'Where card payment is available, click Subscribe & Pay to pay securely by card. The card is saved by Stripe for automatic renewals; MW Realty never stores your card number.'],
        ['icon' => 'fa-paper-plane', 'title' => 'Upgrade requests', 'text' => 'Where a plan is not paid online, click Upgrade Now to send a request. Super Admin reviews it, and it shows as Request Pending until then.'],
        ['icon' => 'fa-arrows-up-down', 'title' => 'Change a subscription', 'text' => 'With a card subscription, click Upgrade or Downgrade. A window shows exactly what will be charged before you click Confirm.'],
        ['icon' => 'fa-rotate', 'title' => 'Auto-renew', 'text' => 'Click Cancel auto-renew to keep your plan until the end of the period you paid for, then move to Free. Click Resume auto-renew to switch it back on.'],
        ['icon' => 'fa-file-invoice', 'title' => 'Payment history and invoices', 'text' => 'Click Payment history to see your payments from the last 12 months. Open any Invoice, or download it as a PDF.'],
    ],
    'steps' => [
        ['title' => 'Get approved first', 'text' => 'Choosing or buying a plan unlocks after your KYC is approved. Payment history and invoices are always available.'],
        ['title' => 'Pick a plan', 'text' => 'Choose Monthly or Yearly, apply a coupon if you have one, and find the plan that fits.'],
        ['title' => 'Pay or request', 'text' => 'Click Subscribe & Pay and complete the card payment, or click Upgrade Now to send a request for review.'],
        ['title' => 'Start using it', 'text' => 'A card payment activates the plan straight away. A request is activated once Super Admin approves it.'],
        ['title' => 'Manage it later', 'text' => 'Come back here to upgrade, downgrade, turn auto-renew off or on, and get your invoices.'],
    ],
    'tips' => [
        'Upgrades on a card subscription start straight away. Downgrades start at your next renewal date, and you can click Keep to stay on your current plan.',
        'You can have only one plan request pending at a time.',
        'Agents in an agency whose plan covers them see Covered by your agency here, with nothing to buy. Listings they add count towards the agency\'s plan.',
        'If a card payment fails, your plan shows Payment failed. Update your card to keep your plan.',
    ],
];
