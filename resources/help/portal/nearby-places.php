<?php

return [
    'title' => 'Nearby Places',
    'icon' => 'fa-map-marker-alt',
    'intro' => 'Landmarks such as schools, hospitals, restaurants and attractions that you can tag on your properties. Everyone can use every place, so the list grows as agents and agencies add to it.',
    'features' => [
        ['icon' => 'fa-filter', 'title' => 'All, My Places and Shared', 'text' => 'Switch tabs to see every place, only the ones you added, or the shared ones added by MW Realty.'],
        ['icon' => 'fa-plus', 'title' => 'Add a place', 'text' => 'Click Add Place and enter its type, name, address and map location (latitude and longitude).'],
        ['icon' => 'fa-language', 'title' => 'Names in each language', 'text' => 'When the site has more than one language, enter the name and address for each language tab.'],
        ['icon' => 'fa-building', 'title' => 'See where it is used', 'text' => 'The Properties column shows how many properties are tagged with each place.'],
        ['icon' => 'fa-toggle-on', 'title' => 'Switch on or off', 'text' => 'Turn a place off to hide it from the picker without deleting it.'],
        ['icon' => 'fa-edit', 'title' => 'Edit or delete your own', 'text' => 'You can edit, switch off or delete only the places you added. Places added by others show a lock.'],
    ],
    'steps' => [
        ['title' => 'Check the list first', 'text' => 'Look through All to see if the place already exists, so you do not add it twice.'],
        ['title' => 'Click Add Place', 'text' => 'Pick a Type, then fill in the Name, Address, Latitude and Longitude.'],
        ['title' => 'Save Place', 'text' => 'Leave Active ticked so the place can be picked on properties.'],
        ['title' => 'Tag it on a property', 'text' => 'In the property form, open the Nearby Places tab, choose a type, then the place, and click Add.'],
    ],
    'tips' => [
        'A place that is tagged on any property cannot be deleted. Remove it from those properties first, or simply switch it off.',
        'Missing a type? Ask MW Realty to add it through a support ticket.',
        'From the property form, New Place opens this page in a new tab. After saving, pick the type again to see your new place.',
    ],
];
