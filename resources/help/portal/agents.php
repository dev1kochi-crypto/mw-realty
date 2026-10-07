<?php

return [
    'title' => 'Agents',
    'icon' => 'fa-user-tie',
    'intro' => 'Build and manage your agency\'s team of agents. Add new agents, invite existing ones, answer join requests, and choose how new leads are shared out.',
    'features' => [
        ['icon' => 'fa-user-plus', 'title' => 'Add or invite agents', 'text' => 'Click Add / Invite Agent to create a brand-new agent account, or invite an agent who already has an account using their email, mobile number or agent ID.'],
        ['icon' => 'fa-table-list', 'title' => 'Roster tabs', 'text' => 'Switch between Active, Pending & Invited, Join Requests and History to see your agents at each stage.'],
        ['icon' => 'fa-user-check', 'title' => 'Answer join requests', 'text' => 'Agents can ask to join your agency. Click Accept or Decline on the Join Requests tab. The number on Agents in the sidebar shows how many are waiting.'],
        ['icon' => 'fa-rotate', 'title' => 'Lead assignment', 'text' => 'Click Change on the Lead assignment bar. Choose Automatic to share new leads in turn (round robin) across your active agents, or Manual to assign every new lead yourself.'],
        ['icon' => 'fa-toggle-on', 'title' => 'Choose which leads rotate', 'text' => 'In Automatic mode, switch Property enquiries, Generic enquiries and Facebook leads on or off. Leads that are switched off wait in Unassigned for you.'],
        ['icon' => 'fa-share-nodes', 'title' => 'Distribute unassigned leads', 'text' => 'The Unassigned agency leads card shows leads with no agent. Use its button to share them all out across your active agents in one go.'],
        ['icon' => 'fa-pause', 'title' => 'Suspend, reactivate or remove', 'text' => 'Suspend an agent to stop new leads going to them, then Reactivate later. Remove ends their membership and lets you hand their agency listings to another agent.'],
        ['icon' => 'fa-gauge', 'title' => 'Agent slots', 'text' => 'The Agent slots counter shows how many agents your plan allows and how many you use. Upgrade your plan to add more.'],
    ],
    'steps' => [
        ['title' => 'Open Add / Invite Agent', 'text' => 'Invite an existing agent by email, mobile number or agent ID, or fill in Add a new agent with their name, email and phone. Identity documents are optional.'],
        ['title' => 'Wait for approval', 'text' => 'New agents and invitations appear under Pending & Invited. An invited agent must accept first, then Super Admin approves every new agent.'],
        ['title' => 'Agent sets a password', 'text' => 'A brand-new agent gets a secure email link after approval to set their own password. You never set or see it. The link works once and expires after 7 days.'],
        ['title' => 'Set up lead assignment', 'text' => 'Click Change on the Lead assignment bar, pick Automatic or Manual, and click Save changes.'],
        ['title' => 'Manage your team', 'text' => 'Click an agent\'s name to see their details, assigned agency listings and lead count, and to remove them if needed.'],
    ],
    'tips' => [
        'Enquiries on a listing with an assigned agent always go to that agent, whatever lead assignment mode you choose.',
        'If the email or mobile you enter already belongs to an independent agent, you are offered Send agency invitation instead of creating a duplicate account.',
        'Agents your agency adds or invites move onto your plan when approved, if your plan includes team agents. A paid plan of their own stops with no refund.',
        'Removing an agent keeps their account, personal listings and lead history. Leads already assigned to them stay with them, so reassign them from Leads if needed.',
    ],
];
