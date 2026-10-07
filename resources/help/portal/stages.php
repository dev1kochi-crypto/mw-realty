<?php

return [
    'title' => 'Stage',
    'icon' => 'fa-layer-group',
    'intro' => 'Stages are the steps of your lead pipeline, such as New, Negotiation or Closed. Set them up here so every lead shows clearly where it stands.',
    'features' => [
        ['icon' => 'fa-plus', 'title' => 'Add your own stages', 'text' => 'Click Add Stage, type a name and pick a colour. Stages you add are yours only, and you can edit or delete them.'],
        ['icon' => 'fa-globe', 'title' => 'MW Realty stages', 'text' => 'Stages marked MW Realty are set for every account. You can use them on your leads, but you cannot change them (they show View only).'],
        ['icon' => 'fa-grip-lines', 'title' => 'Drag to reorder', 'text' => 'Drag your own rows up or down to set the order of your pipeline. This is the order stages appear in when you pick one for a lead.'],
        ['icon' => 'fa-flag-checkered', 'title' => 'Counts as closed', 'text' => 'Switch on Counts as closed for stages where the lead is finished, won or lost. The Sales report uses this.'],
        ['icon' => 'fa-star', 'title' => 'Default stage', 'text' => 'Click Set default on one of your stages. New leads that arrive without a stage are placed in your default stage.'],
        ['icon' => 'fa-users', 'title' => 'See the leads in a stage', 'text' => 'The Leads column shows how many of your leads use each stage. Click the number to see those leads, search them, and remove the stage from some or all of them.'],
    ],
    'steps' => [
        ['title' => 'Check the existing stages', 'text' => 'Look at the list first. The MW Realty stages may already cover most of what you need.'],
        ['title' => 'Add a stage', 'text' => 'Click Add Stage, enter a name (for example Site Visit Scheduled), choose a colour, and turn on Counts as closed if it ends a deal. Then click Add Stage.'],
        ['title' => 'Put them in order', 'text' => 'Drag your rows so the stages follow the way you work a lead, from first contact to closing.'],
        ['title' => 'Pick a default', 'text' => 'Click Set default on the stage new leads should start in.'],
        ['title' => 'Edit or delete', 'text' => 'Use Edit to rename or recolour a stage. Use Delete to remove one you no longer need.'],
    ],
    'tips' => [
        'A stage still used by leads cannot be deleted. Click its lead count, remove the stage from those leads, then delete it.',
        'The default stage cannot be deleted. Set another stage as default first.',
        'In the Sales report, a closed stage with a name like Lost, Dropped or Cancelled counts as lost. Other closed stages count as won.',
        'Each name can be used only once, so you cannot add a stage with the same name as an existing one.',
    ],
];
