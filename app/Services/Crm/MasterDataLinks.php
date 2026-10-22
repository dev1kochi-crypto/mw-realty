<?php

namespace App\Services\Crm;

use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStage;
use App\Models\LeadTag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Which leads use a Stage / Tag / Source, and taking it off them — so an item still in use can be
 * cleared and then deleted. Always limited to the leads the viewer can see (null = Super Admin, all).
 */
class MasterDataLinks
{
    public const TYPES = [
        'stages' => ['model' => LeadStage::class, 'label' => 'stage', 'column' => 'stage_id'],
        'sources' => ['model' => LeadSource::class, 'label' => 'source', 'column' => 'source_id'],
        'tags' => ['model' => LeadTag::class, 'label' => 'tag', 'column' => null], // lead_tag_pivot
    ];

    /** The leads (visible to $viewerOwnerId) that use this item. */
    public function leads(string $type, Model $item, ?int $viewerOwnerId): Builder
    {
        $column = self::TYPES[$type]['column'];

        return Lead::forOwner($viewerOwnerId)
            ->when($column,
                fn ($q) => $q->where("leads.{$column}", $item->id),
                fn ($q) => $q->whereIn('leads.id', DB::table('lead_tag_pivot')->where('lead_tag_id', $item->id)->select('lead_id')));
    }

    /** withCount() constraint so list pages show how many of the viewer's leads use each item. */
    public function countConstraint(?int $viewerOwnerId): array
    {
        return ['leads' => fn ($q) => $q->forOwner($viewerOwnerId)];
    }

    /**
     * Takes the item off the given leads (or all of the viewer's leads using it when $leadIds is null).
     * Stage / source changes go through the model so the lead's activity history records them.
     * Returns how many leads were changed.
     */
    public function remove(string $type, Model $item, ?int $viewerOwnerId, ?array $leadIds): int
    {
        $query = $this->leads($type, $item, $viewerOwnerId)
            ->when($leadIds !== null, fn ($q) => $q->whereIn('leads.id', $leadIds ?: [0]));
        $column = self::TYPES[$type]['column'];

        if (!$column) {
            $ids = $query->pluck('leads.id');
            DB::table('lead_tag_pivot')->where('lead_tag_id', $item->id)->whereIn('lead_id', $ids)->delete();

            return $ids->count();
        }

        $changed = 0;
        $query->select('leads.*')->chunkById(200, function ($leads) use ($column, &$changed) {
            foreach ($leads as $lead) {
                $lead->{$column} = null;
                $lead->save();
                $changed++;
            }
        }, 'leads.id', 'id');

        return $changed;
    }
}
