<?php

return [
    'title' => 'Leads',
    'icon' => 'fa-address-book',
    'intro' => 'Every enquiry about your listings lands here. Track each lead through your sales stages, keep notes, and never lose a buyer or tenant.',
    'features' => [
        ['icon' => 'fa-filter', 'title' => 'Filter and search', 'text' => 'Use the filter bar to search by name, email or phone, and narrow by stage, source, tag, agent or the date received. The Total, Active 24h, Last 7 days and Website Insights cards work as quick filters.'],
        ['icon' => 'fa-layer-group', 'title' => 'Stages and tags', 'text' => 'Change a lead\'s stage right in the table, or add tags. To update many at once, tick leads and click Assign Stage or Assign Tag.'],
        ['icon' => 'fa-plus', 'title' => 'Add a lead', 'text' => 'Click Add Lead to record an enquiry you got by phone, email or in person. Fill in the name, contact details, stage, source and tags.'],
        ['icon' => 'fa-id-card', 'title' => 'Lead details page', 'text' => 'Click a lead to open it. Use the Profile, Timeline, Summary and Insights tabs, edit details in place, add notes and contacts, and Call, WhatsApp or Email in one click.'],
        ['icon' => 'fa-file-import', 'title' => 'Import and export', 'text' => 'Click Import to upload leads from an Excel or CSV file (up to 10 MB). Tick leads and click Export to download them as an Excel file.'],
        ['icon' => 'fa-table-columns', 'title' => 'Table Fields', 'text' => 'Choose which columns the leads table shows, such as Email, Owner, Message or Notes. Lead and Phone are always shown.'],
        ['icon' => 'fa-trash-can-arrow-up', 'title' => 'Deleted leads', 'text' => 'Deleted leads are not lost straight away. Open View deleted leads to Restore them or Delete Permanently.'],
        ['icon' => 'fa-rotate', 'title' => 'Lead assignment (agencies)', 'text' => 'Agencies can set Lead Assignment to Auto (round robin across active agents) or Manual, assign a lead to an agent from its page, and use Distribute to share out unassigned leads.'],
    ],
    'steps' => [
        ['title' => 'A new lead arrives', 'text' => 'Enquiries from your listings appear at the top of the table. You can also add one yourself with Add Lead.'],
        ['title' => 'Open and contact the lead', 'text' => 'Click the lead to see their details and message, then Call, WhatsApp or Email them.'],
        ['title' => 'Move it through your stages', 'text' => 'Update the stage as the deal moves forward, for example from New to Site Visit to Closed Won.'],
        ['title' => 'Keep notes', 'text' => 'Add a note after each call or meeting. Every change is recorded on the Timeline tab.'],
        ['title' => 'Close or tidy up', 'text' => 'Move won or lost deals to a closed stage, or mark a lead Inactive. Delete leads you no longer need.'],
    ],
    'tips' => [
        'If a new or imported lead has the same email or phone as an existing lead, the existing lead is updated instead of creating a duplicate. A matching deleted lead is restored.',
        'A lead\'s source is fixed once it is captured and cannot be edited.',
        'Use Download the template in the Import window so your columns match. Rows with problems are skipped and listed after the import.',
        'Agents inside an agency see the agency leads assigned to them and can work them, but only the agency can delete them.',
    ],
];
