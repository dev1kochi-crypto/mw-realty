<?php

return [
    'title' => 'Sold Listings',
    'icon' => 'fa-handshake',
    'intro' => 'Every listing you marked as sold or rented, with the price, date, buyer or tenant and the deal documents. These listings are off the website, and you can put one back on the market at any time.',
    'features' => [
        ['icon' => 'fa-chart-simple', 'title' => 'Totals at a glance', 'text' => 'The cards at the top show how many listings are sold and rented, and the total sales and rental value.'],
        ['icon' => 'fa-filter', 'title' => 'Search and filter', 'text' => 'Search by title, reference number, address, city or buyer name, and choose Sold, Rented or both.'],
        ['icon' => 'fa-user', 'title' => 'Buyer or tenant linked', 'text' => 'Each deal is linked to a lead. Click the name to open the lead and see its full history.'],
        ['icon' => 'fa-file-contract', 'title' => 'Deal documents', 'text' => 'Open the title deed and the sale contract (Form F / MOU) or Ejari. They are private and only your account and MW Realty admins can open them.'],
        ['icon' => 'fa-key', 'title' => 'Password and notes', 'text' => 'Use the key and note buttons to see the document password or any notes saved with the deal.'],
        ['icon' => 'fa-rotate-left', 'title' => 'Revert', 'text' => 'Put a listing back on the market if the deal falls through.'],
    ],
    'steps' => [
        ['title' => 'Mark a listing sold or rented', 'text' => 'In Properties or Commercial, open a listing\'s actions menu and choose Mark as sold or Mark as rented.'],
        ['title' => 'Enter the deal details', 'text' => 'Add the price, date and, if you like, the commission, lease end date and notes.'],
        ['title' => 'Pick the buyer or tenant', 'text' => 'Choose an existing lead (people who enquired about this listing are shown first) or add a new buyer with a name and an email or phone number.'],
        ['title' => 'Upload the proof', 'text' => 'Upload the title deed and the sale contract (Form F / MOU) or the Ejari for a rental. PDF, JPG or PNG, up to 10 MB each.'],
        ['title' => 'Find it here', 'text' => 'The listing leaves the website, its premium is stopped, and the lead moves to your won stage. It now shows on this page.'],
    ],
    'tips' => [
        'Sold Listings appears in the menu only after you have marked at least one listing as sold or rented.',
        'Reverting removes the deal and its documents. The listing goes back on the website only if it was active before and its permit still allows it. The lead keeps its history.',
        'Use the Sales Report button for a fuller view of your sales over time.',
    ],
];
