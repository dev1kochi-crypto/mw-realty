<?php

return [
    'title' => 'Listing Settings',
    'icon' => 'fa-stamp',
    'intro' => 'Set up the watermark that is stamped on your listing photos when you upload them. Use your logo or a line of text to brand your photos.',
    'features' => [
        ['icon' => 'fa-toggle-on', 'title' => 'Turn it on or off', 'text' => 'Use the switch to add your watermark to every listing photo you upload from now on.'],
        ['icon' => 'fa-image', 'title' => 'Logo or text', 'text' => 'Upload a logo (PNG, JPG or WEBP, up to 4 MB) or type up to 60 characters of text and choose its colour.'],
        ['icon' => 'fa-border-all', 'title' => 'Position', 'text' => 'Place the watermark in any of nine spots, from top left to bottom right.'],
        ['icon' => 'fa-sliders', 'title' => 'Opacity and size', 'text' => 'Use the sliders to make it lighter or stronger, and smaller or larger on the photo.'],
        ['icon' => 'fa-eye', 'title' => 'Live preview', 'text' => 'See exactly how it will look on a sample listing photo as you change the settings.'],
        ['icon' => 'fa-users', 'title' => 'Agency and agent watermarks', 'text' => 'Super Admin sets the default watermark and can see, on a second tab, every agency and agent that has set up their own.'],
    ],
    'steps' => [
        ['title' => 'Switch the watermark on', 'text' => 'Turn on the switch at the top of the settings.'],
        ['title' => 'Choose Logo or Text', 'text' => 'Upload your logo, or enter your text and pick a colour.'],
        ['title' => 'Adjust the look', 'text' => 'Pick a position and set the opacity and size, checking the preview as you go.'],
        ['title' => 'Save changes', 'text' => 'From now on, every listing photo you upload gets this watermark.'],
    ],
    'tips' => [
        'The watermark is added only to photos uploaded after you save. Photos already uploaded are not changed.',
        'If you have not turned on your own watermark, the MW Realty default watermark is used on your photos.',
        'Agents in an agency use the agency\'s watermark. Only the agency can change it.',
        'A PNG logo with a transparent background gives the cleanest result.',
    ],
];
