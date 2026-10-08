<?php

namespace App\Services\Crm;

use App\Models\LeadTablePreference;
use Illuminate\Support\Facades\Auth;

/**
 * Owns the field configuration for the Leads DataTable. Keeping this here
 * means controller actions only resolve and return the active preference.
 */
class LeadTablePreferenceService
{
    public const LEAD = 'lead';
    public const EMAIL = 'email';
    public const PHONE = 'phone';
    public const OWNER = 'owner';
    public const AGENT = 'agent';
    public const STAGE = 'stage';
    public const STATUS = 'status';
    public const SOURCE = 'source';
    public const TAGS = 'tags';
    public const MESSAGE = 'message';
    public const NOTES = 'notes';
    public const RECEIVED = 'received';
    public const COMPANY = 'company';
    public const COUNTRY = 'country';
    public const PROPERTY = 'property';
    public const CAMPAIGN = 'campaign';
    public const ENQUIRIES = 'enquiries';
    public const LAST_ENQUIRY = 'last_enquiry';
    public const UPDATED = 'updated';
    public const PAGE = 'page';
    public const ASSIGNED = 'assigned';
    public const CLOSED = 'closed';
    // Website insights — the tracked visitor the lead came from (blank for other leads).
    public const VIEWS = 'views';
    public const TIME_ON_SITE = 'time_on_site';
    public const SEARCHES = 'searches';
    public const CHATS = 'chats';
    public const LAST_ACTIVE = 'last_active';

    /** Website-insight fields — LeadService only adds their (sub-query) counts when one is shown or sorted. */
    public const INSIGHT_COLUMNS = [self::VIEWS, self::TIME_ON_SITE, self::SEARCHES, self::CHATS, self::LAST_ACTIVE];

    /** Table Fields groups, in the order the "Add fields" pane lists them. */
    public const GROUPS = [
        'details' => ['label' => 'Contact details', 'icon' => 'fa-address-card'],
        'pipeline' => ['label' => 'Pipeline', 'icon' => 'fa-diagram-project'],
        'enquiry' => ['label' => 'Enquiry', 'icon' => 'fa-envelope-open-text'],
        'insights' => ['label' => 'Website insights', 'icon' => 'fa-chart-line'],
        'dates' => ['label' => 'Dates', 'icon' => 'fa-calendar-days'],
    ];

    public function __construct(private readonly OwnerContext $ownerContext)
    {
    }

    /** Fields available to the current viewer in their default order (each viewer can reorder). */
    public function availableColumns(): array
    {
        $columns = [
            self::LEAD => [
                'label' => 'Lead',
                'group' => 'details',
                'field' => 'name',
                'locked' => true,
                'default' => true,
                'sortable' => true,
            ],
            self::EMAIL => [
                'label' => 'Email',
                'group' => 'details',
                'field' => 'email',
                'locked' => false,
                'default' => false,
                'sortable' => true,
            ],
            self::PHONE => [
                'label' => 'Phone',
                'group' => 'details',
                'field' => 'formatted_phone',
                'locked' => true,
                'default' => true,
                'sortable' => true,
            ],
        ];

        if ($this->ownerContext->isAdmin()) {
            $columns[self::OWNER] = [
                'label' => 'Owner',
                'group' => 'pipeline',
                'field' => 'owner.display_name',
                'locked' => false,
                'default' => true,
                'sortable' => true,
            ];
        }

        // Who is working each lead — an agency's core view (always shown), optional for Super Admin.
        $viewer = $this->ownerContext->owner();
        if ($this->ownerContext->isAdmin() || $viewer?->isAgency()) {
            $columns[self::AGENT] = [
                'label' => 'Assigned Agent',
                'group' => 'pipeline',
                'field' => 'agent.name',
                'locked' => (bool) $viewer?->isAgency(),
                'default' => true,
                'sortable' => true,
            ];
        }

        $columns += [
            self::STAGE => [
                'label' => 'Stage',
                'group' => 'pipeline',
                'field' => 'stage.name',
                'locked' => false,
                'default' => true,
                'sortable' => true,
            ],
            self::STATUS => [
                'label' => 'Status',
                'group' => 'pipeline',
                'field' => 'status',
                'locked' => false,
                'default' => false,
                'sortable' => true,
            ],
            self::SOURCE => [
                'label' => 'Source',
                'group' => 'enquiry',
                'field' => 'source.name',
                'locked' => false,
                'default' => true,
                'sortable' => true,
            ],
            self::TAGS => [
                'label' => 'Tags',
                'group' => 'pipeline',
                'field' => 'tags',
                'locked' => false,
                'default' => true,
                'sortable' => true,
            ],
            self::MESSAGE => [
                'label' => 'Message',
                'group' => 'enquiry',
                'field' => 'message',
                'locked' => false,
                'default' => false,
                'sortable' => true,
            ],
            self::NOTES => [
                'label' => 'Notes',
                'group' => 'pipeline',
                'field' => 'notes_history_count',
                'locked' => false,
                'default' => false,
                'sortable' => true,
            ],
            self::RECEIVED => [
                'label' => 'Received',
                'group' => 'dates',
                'field' => 'created_at',
                'locked' => false,
                'default' => true,
                'sortable' => true,
            ],
            self::COMPANY => [
                'label' => 'Company',
                'group' => 'details',
                'field' => 'company',
                'locked' => false,
                'default' => false,
                'sortable' => true,
            ],
            self::COUNTRY => [
                'label' => 'Country',
                'group' => 'details',
                'field' => 'country',
                'locked' => false,
                'default' => false,
                'sortable' => true,
            ],
            self::PROPERTY => [
                'label' => 'Property',
                'group' => 'enquiry',
                'field' => 'property.title',
                'locked' => false,
                'default' => false,
                'sortable' => false,
            ],
            self::CAMPAIGN => [
                'label' => 'Campaign',
                'group' => 'enquiry',
                'field' => 'extra_fields.facebook_campaign',
                'locked' => false,
                'default' => false,
                'sortable' => false,
            ],
            self::ENQUIRIES => [
                'label' => 'Enquiries',
                'group' => 'enquiry',
                'field' => 'enquiry_count',
                'locked' => false,
                'default' => false,
                'sortable' => true,
            ],
            self::LAST_ENQUIRY => [
                'label' => 'Last Enquiry',
                'group' => 'dates',
                'field' => 'last_enquired_at',
                'locked' => false,
                'default' => false,
                'sortable' => true,
            ],
            self::UPDATED => [
                'label' => 'Last Updated',
                'group' => 'dates',
                'field' => 'updated_at',
                'locked' => false,
                'default' => false,
                'sortable' => true,
            ],
            self::PAGE => [
                'label' => 'Enquiry Page',
                'group' => 'enquiry',
                'field' => 'page_url',
                'locked' => false,
                'default' => false,
                'sortable' => false,
            ],
        ];

        if (isset($columns[self::AGENT])) {
            $columns[self::ASSIGNED] = [
                'label' => 'Assigned On',
                'group' => 'dates',
                'field' => 'assigned_at',
                'locked' => false,
                'default' => false,
                'sortable' => true,
            ];
        }

        $columns += [
            self::CLOSED => [
                'label' => 'Closed On',
                'group' => 'dates',
                'field' => 'closed_at',
                'locked' => false,
                'default' => false,
                'sortable' => true,
            ],
            self::VIEWS => [
                'label' => 'Property Views',
                'group' => 'insights',
                'field' => 'visitor_property_views',
                'locked' => false,
                'default' => false,
                'sortable' => true,
            ],
            self::TIME_ON_SITE => [
                'label' => 'Time on Site',
                'group' => 'insights',
                'field' => 'visitor_seconds',
                'locked' => false,
                'default' => false,
                'sortable' => true,
            ],
            self::SEARCHES => [
                'label' => 'Searches',
                'group' => 'insights',
                'field' => 'visitor_searches',
                'locked' => false,
                'default' => false,
                'sortable' => true,
            ],
            self::CHATS => [
                'label' => 'AI Chats',
                'group' => 'insights',
                'field' => 'visitor_chats',
                'locked' => false,
                'default' => false,
                'sortable' => true,
            ],
            self::LAST_ACTIVE => [
                'label' => 'Last Active on Site',
                'group' => 'insights',
                'field' => 'visitor_last_seen',
                'locked' => false,
                'default' => false,
                'sortable' => true,
            ],
        ];

        return $columns;
    }

    public function getUserColumns(): array
    {
        $actor = $this->actor();
        $columns = $actor
            ? LeadTablePreference::query()
                ->where('actor_type', $actor['type'])
                ->where('actor_id', $actor['id'])
                ->value('columns')
            : null;

        return $this->normaliseColumns(is_array($columns) ? $columns : $this->defaultColumns());
    }

    /** Stores a valid, normalised choice and returns it for the UI. */
    public function saveUserColumns(array $columns): array
    {
        $columns = $this->normaliseColumns($columns);
        $actor = $this->actor();

        abort_unless($actor, 403);

        LeadTablePreference::updateOrCreate(
            ['actor_type' => $actor['type'], 'actor_id' => $actor['id']],
            ['columns' => $columns],
        );

        return $columns;
    }

    private function defaultColumns(): array
    {
        return array_keys(array_filter(
            $this->availableColumns(),
            fn (array $column) => $column['default'],
        ));
    }

    /**
     * The shown fields in the viewer's order (dragged in Table Fields or on the table header).
     * Lead is always first; other locked fields (Phone…) are forced on, added after it when missing;
     * obsolete / unavailable / repeated values are dropped.
     */
    private function normaliseColumns(array $columns): array
    {
        $available = $this->availableColumns();
        $ordered = array_values(array_unique(array_filter(
            array_map('strval', $columns),
            fn (string $column) => isset($available[$column]) && $column !== self::LEAD,
        )));

        foreach (array_reverse(array_keys($available)) as $key) {
            if ($available[$key]['locked'] && $key !== self::LEAD && !in_array($key, $ordered, true)) {
                array_unshift($ordered, $key);
            }
        }

        return [self::LEAD, ...$ordered];
    }

    /**
     * Every available field for the Table Fields list: the shown ones in the viewer's order, then
     * the hidden ones in their default order — so ticking a hidden field adds it at the end.
     */
    public function orderedAvailableColumns(): array
    {
        // array_replace keeps the first array's key order and appends the rest.
        return array_replace(array_fill_keys($this->getUserColumns(), null), $this->availableColumns());
    }

    private function actor(): ?array
    {
        if ($portalUser = $this->ownerContext->owner()) {
            return ['type' => 'portal', 'id' => $portalUser->id];
        }

        if ($cmsUser = Auth::guard('cms')->user()) {
            return ['type' => 'cms', 'id' => $cmsUser->id];
        }

        return null;
    }
}
