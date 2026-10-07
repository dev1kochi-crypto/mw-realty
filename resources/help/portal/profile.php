<?php

return [
    'title' => 'My Profile',
    'icon' => 'fa-user-edit',
    'intro' => 'Keep your details, licenses and KYC documents up to date, and submit them for approval. Approval unlocks Leads, Reports and listings, and your profile also feeds your public page on the website.',
    'features' => [
        ['icon' => 'fa-list-check', 'title' => 'Profile sections', 'text' => 'Fill in each section from the menu on the left, such as Public details, Compliance, About and Identity for agents, or Contact information, Corporate licenses and Other information for agencies. Click Edit on a section, then Save.'],
        ['icon' => 'fa-folder-open', 'title' => 'Documents', 'text' => 'Upload your KYC documents, such as Emirates ID, Passport, RERA documents and Trade License. Each one shows Not submitted, Awaiting Review, Verified or Needs Re-upload.'],
        ['icon' => 'fa-paper-plane', 'title' => 'Submit for approval', 'text' => 'When your details and documents are ready, click Submit for Approval. The progress card shows each step: Details & documents, Submit for approval, Admin review and Approved.'],
        ['icon' => 'fa-image', 'title' => 'Photo or logo', 'text' => 'Click your photo at the top to add or change a profile photo (agents) or logo (agencies). It appears on your public page and on your listings\' contact card. Use an image at least 120 x 120 pixels.'],
        ['icon' => 'fa-globe', 'title' => 'Your public page', 'text' => 'See a preview of how buyers see you, and open it with View public page once your account is approved. The SEO section lets you set optional search engine details.'],
        ['icon' => 'fa-envelope', 'title' => 'Change login email', 'text' => 'Enter your new email and click Send Code. Enter the 4-digit code sent to the new address and click Verify & Update.'],
        ['icon' => 'fa-chart-simple', 'title' => 'Complete your profile', 'text' => 'A checklist shows what is still missing and how complete your profile is. Click an item to jump straight to it.'],
    ],
    'steps' => [
        ['title' => 'Fill in your details', 'text' => 'Work through each section until none show missing.'],
        ['title' => 'Upload your documents', 'text' => 'Open Documents and upload each file. JPG, PNG or PDF, up to 4 MB each.'],
        ['title' => 'Submit for approval', 'text' => 'Click Submit for Approval and confirm. Our team is notified and your KYC shows as waiting for review.'],
        ['title' => 'Wait for the review', 'text' => 'Review usually takes 1 to 2 working days. If our team asks for changes or rejects the application, the reason is shown on this page.'],
        ['title' => 'Fix and resubmit if needed', 'text' => 'Make the requested changes, then click Resubmit for Approval.'],
    ],
    'tips' => [
        'If you change licenses, identity details or documents while your KYC is waiting for review, you will need to submit it again.',
        'Identity details are private. They are only used to verify your account and are never shown publicly.',
        'Write your description in your own language. It is translated automatically for other site languages unless you write the Arabic one yourself.',
        'After changing your login email, you are logged out and must sign in again with the new email.',
    ],
];
