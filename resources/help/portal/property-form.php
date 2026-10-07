<?php

return [
    'title' => 'Add / Edit Property',
    'icon' => 'fa-pen-to-square',
    'intro' => 'This form holds everything about one listing, split into tabs on the left. The advertising permit in Core details decides when the listing can appear on the website.',
    'features' => [
        ['icon' => 'fa-file-shield', 'title' => 'Permit and Validate', 'text' => 'In Core details, choose the Emirate (and for Dubai the Permit type: RERA, DTCM or None). Enter the permit number and click Validate to check it with DLD or ADREC.'],
        ['icon' => 'fa-lock', 'title' => 'Locked fields', 'text' => 'A verified permit fills in details such as offering type, property type, location, bedrooms and size, and locks them with a padlock. They must match the permit. Raise a ticket if one needs changing.'],
        ['icon' => 'fa-language', 'title' => 'Content language', 'text' => 'Switch languages in the Content language bar. With Auto-fill on, text you type in the first language is translated into the others, or click Translate now. You can still edit any language by hand.'],
        ['icon' => 'fa-list-check', 'title' => 'Property Details', 'text' => 'Add the title, key features and description, plus rooms, size, price and other specifications. Unit number and Owner name are for internal use only and never shown on the website.'],
        ['icon' => 'fa-user-tie', 'title' => 'Agent & Agency', 'text' => 'Agencies can assign one of their active agents, or leave it empty so enquiries are shared across the agency. Agents are always the agent on their own listings.'],
        ['icon' => 'fa-map-pin', 'title' => 'Location and Nearby Places', 'text' => 'Enter the address, city, country and map coordinates. In Nearby Places, pick a type and a place and click Add, or create a New Place if it is missing.'],
        ['icon' => 'fa-swimming-pool', 'title' => 'Amenities, Easy Access, Attributes', 'text' => 'Tick everything that applies from each list. Missing an option? Raise a ticket and the team will add it.'],
        ['icon' => 'fa-images', 'title' => 'Images, Floor Plans and Brochure', 'text' => 'Drop in photos and reorder them; the first one is the featured image. Add floor plan rows, a downloadable floor plan file and a brochure that visitors get after sharing their contact details.'],
    ],
    'steps' => [
        ['title' => 'Fill in Core details', 'text' => 'Choose the emirate and permit, then category, offering type, property type and location. Add availability dates if it is not available immediately.'],
        ['title' => 'Validate the permit', 'text' => 'Enter the permit number, click Validate, and add the expiry date and QR code if asked. A green Verification successful message means it passed.'],
        ['title' => 'Complete the other tabs', 'text' => 'Add the description, price, location, photos and the rest. Fields marked with a red star are required, and the form takes you to anything missing when you save.'],
        ['title' => 'Save', 'text' => 'Click Save Property (or Update Property). Leave Active (visible on site) on if you want it live as soon as it is allowed.'],
        ['title' => 'Go live or wait for approval', 'text' => 'A verified listing can go live straight away. DTCM and None (DIFC / JAFZA) listings, and any listing the form says needs approval, are checked by MW Realty first and you are told by email.'],
    ],
    'tips' => [
        'One permit covers one listing only, and the same unit cannot be listed twice for the same purpose.',
        'A DTCM permit is for holiday homes, so those listings can only be for rent.',
        'The listing goes offline automatically when the permit expires. When it is close to expiry, the locked fields open again so you can enter and validate the renewed permit.',
        'If your listing needs MW Realty approval, changing the permit, price, offering type, property type, bedrooms or size sends it back for approval. Edits to text and photos do not.',
    ],
];
