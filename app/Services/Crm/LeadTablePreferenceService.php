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
    public const STAGE = 'stage';
    public const STATUS = 'status';
    public const SOURCE = 'source';
    public const TAGS = 'tags';
    public const MESSAGE = 'message';
    public const NOTES = 'notes';
    public const RECEIVED = 'received';

    public function __construct(private readonly OwnerContext $ownerContext)
    {
    }

    /** Fields available to the current viewer in display order. */
    public function availableColumns(): array
    {
        $columns = [
            self::LEAD => [
                'label' => 'Lead',
                'field' => 'name',
                'locked' => true,
                'default' => true,
                'sortable' => true,
            ],
            self::EMAIL => [
                'label' => 'Email',
                'field' => 'email',
                'locked' => false,
                'default' => false,
                'sortable' => true,
            ],
            self::PHONE => [
                'label' => 'Phone',
                'field' => 'formatted_phone',
                'locked' => true,
                'default' => true,
                'sortable' => true,
            ],
        ];

        if ($this->ownerContext->isAdmin()) {
            $columns[self::OWNER] = [
                'label' => 'Owner',
                'field' => 'owner.display_name',
                'locked' => false,
                'default' => true,
                'sortable' => true,
            ];
        }

        $columns += [
            self::STAGE => [
                'label' => 'Stage',
                'field' => 'stage.name',
                'locked' => false,
                'default' => true,
                'sortable' => true,
            ],
            self::STATUS => [
                'label' => 'Status',
                'field' => 'status',
                'locked' => false,
                'default' => false,
                'sortable' => true,
            ],
            self::SOURCE => [
                'label' => 'Source',
                'field' => 'source.name',
                'locked' => false,
                'default' => true,
                'sortable' => true,
            ],
            self::TAGS => [
                'label' => 'Tags',
                'field' => 'tags',
                'locked' => false,
                'default' => true,
                'sortable' => true,
            ],
            self::MESSAGE => [
                'label' => 'Message',
                'field' => 'message',
                'locked' => false,
                'default' => false,
                'sortable' => true,
            ],
            self::NOTES => [
                'label' => 'Notes',
                'field' => 'notes_history_count',
                'locked' => false,
                'default' => false,
                'sortable' => true,
            ],
            self::RECEIVED => [
                'label' => 'Received',
                'field' => 'created_at',
                'locked' => false,
                'default' => true,
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

    /** Forces every locked field (Lead, Phone) on and ignores obsolete/unavailable values. */
    private function normaliseColumns(array $columns): array
    {
        $available = $this->availableColumns();
        $selected = array_fill_keys(array_map('strval', $columns), true);
        foreach ($available as $key => $meta) {
            if ($meta['locked']) {
                $selected[$key] = true;
            }
        }

        return array_values(array_filter(
            array_keys($available),
            fn (string $column) => isset($selected[$column]),
        ));
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
