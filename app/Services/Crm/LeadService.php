<?php

namespace App\Services\Crm;

use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStage;
use App\Models\LeadTag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Single source of truth for Lead querying, filtering, statistics, and
 * owner/tag assignment. Reused by the Lead CRUD controller, both dashboards,
 * and the Import/Export classes so none of them hand-roll their own version
 * of these queries — see the module's CLAUDE-facing summary in the PR notes
 * for why this exists as one service rather than duplicated per-caller.
 */
class LeadService
{
    /**
     * The base query for a given owner (null = every owner, i.e. Super Admin's
     * global view) with the listing/export filters applied. Soft-deleted leads
     * are excluded automatically by Eloquent's default scope.
     *
     * Supported $filters keys: search, status, owner_id, stage_id, source_id,
     * tag_id, date_from, date_to.
     */
    public function filteredQuery(?int $ownerId, array $filters = []): Builder
    {
        $query = Lead::with(['property', 'owner', 'stage', 'source', 'tags'])
            ->forOwner($ownerId)
            ->latest();

        if (!empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['owner_id'])) {
            $query->where('portal_user_id', $filters['owner_id']);
        }

        if (!empty($filters['stage_id'])) {
            $stage = LeadStage::find($filters['stage_id']);

            if ($ownerId === null && $stage) {
                // Global filters use the shared Admin master data. Match all
                // owners' stages with the selected label, not only the shared
                // Admin record's id.
                $query->whereHas('stage', fn ($q) => $q->where('name', $stage->name));
            } else {
                $query->where('stage_id', $filters['stage_id']);
            }
        }

        if (!empty($filters['source_id'])) {
            $source = LeadSource::find($filters['source_id']);

            if ($ownerId === null && $source) {
                // See the equivalent global stage filter above.
                $query->whereHas('source', fn ($q) => $q->where('name', $source->name));
            } else {
                $query->where('source_id', $filters['source_id']);
            }
        }

        if (!empty($filters['tag_id'])) {
            $tag = LeadTag::find($filters['tag_id']);

            if ($ownerId === null && $tag) {
                $query->whereHas('tags', fn ($q) => $q->where('lead_tags.name', $tag->name));
            } else {
                $query->whereHas('tags', fn ($q) => $q->where('lead_tags.id', $filters['tag_id']));
            }
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    public function getFilteredLeads(?int $ownerId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->filteredQuery($ownerId, $filters)->paginate($perPage)->withQueryString();
    }

    /**
     * The single place a Lead is looked up "as owned by" a given scope — the
     * CRUD controller (view/edit/delete/JSON endpoints) is the only caller,
     * so ownership enforcement lives in exactly one query.
     */
    public function getLead(?int $ownerId, $id, bool $withTrashed = false): Lead
    {
        $query = $withTrashed ? Lead::withTrashed() : Lead::with(['property', 'owner', 'stage', 'source', 'tags']);

        return $query->forOwner($ownerId)->findOrFail($id);
    }

    /** Trashed (soft-deleted) leads for this owner, most recently deleted first. */
    public function getTrashedLeads(?int $ownerId, int $perPage = 15): LengthAwarePaginator
    {
        return Lead::onlyTrashed()
            ->with(['property', 'owner', 'stage', 'source'])
            ->forOwner($ownerId)
            ->orderByDesc('deleted_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function getLeadStatistics(?int $ownerId): array
    {
        $counts = Lead::forOwner($ownerId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total' => (int) $counts->sum(),
            'active' => (int) $counts->get('active', 0),
            'inactive' => (int) $counts->get('inactive', 0),
        ];
    }

    public function getLeadsByStage(?int $ownerId): Collection
    {
        return Lead::forOwner($ownerId)
            ->whereNotNull('stage_id')
            ->join('lead_stages', 'lead_stages.id', '=', 'leads.stage_id')
            ->selectRaw('lead_stages.id, lead_stages.name, count(leads.id) as total')
            ->groupBy('lead_stages.id', 'lead_stages.name')
            ->orderBy('lead_stages.order_index')
            ->get();
    }

    public function getLeadsBySource(?int $ownerId): Collection
    {
        return Lead::forOwner($ownerId)
            ->whereNotNull('source_id')
            ->join('lead_sources', 'lead_sources.id', '=', 'leads.source_id')
            ->selectRaw('lead_sources.id, lead_sources.name, count(leads.id) as total')
            ->groupBy('lead_sources.id', 'lead_sources.name')
            ->orderBy('lead_sources.order_index')
            ->get();
    }

    /** Cross-tenant breakdown — meaningful only for the Super Admin's global view. */
    public function getLeadsByOwner(): Collection
    {
        return Lead::join('portal_users', 'portal_users.id', '=', 'leads.portal_user_id')
            ->selectRaw('portal_users.id, portal_users.name, portal_users.company_name, portal_users.type, count(leads.id) as total')
            ->groupBy('portal_users.id', 'portal_users.name', 'portal_users.company_name', 'portal_users.type')
            ->get();
    }

    /** The one place a Lead's owner is (re)assigned — CRUD, import, and any future bulk-assign/API path all call this. */
    public function assignOwner(Lead $lead, int $ownerId): Lead
    {
        $lead->update(['portal_user_id' => $ownerId]);

        return $lead;
    }

    /**
     * Looks up (or creates, scoped to the given owner) LeadTag rows by name and
     * returns their ids — the one place tag resolution happens, used by the
     * manual Create/Edit form and the Excel importer alike.
     *
     * @param  string[]  $tagNames
     * @return int[]
     */
    public function resolveTagIds(int $ownerId, array $tagNames): array
    {
        $ids = [];

        foreach ($tagNames as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $ids[] = LeadTag::firstOrCreate(
                ['portal_user_id' => $ownerId, 'name' => $name],
                ['color' => '#14b8a6']
            )->id;
        }

        return $ids;
    }

    /**
     * @param  int[]  $tagIds  Existing LeadTag ids to attach — for free-text tag
     *                         names (e.g. from an Excel import), resolve them to
     *                         ids with resolveTagIds() first.
     */
    public function createLead(array $data, int $ownerId, array $tagIds = []): Lead
    {
        $lead = Lead::create(array_merge($data, ['portal_user_id' => $ownerId]));

        if (!empty($tagIds)) {
            $lead->tags()->sync($tagIds);
        }

        return $lead;
    }

    /** @param int[]|null $tagIds Pass null to leave tags untouched. */
    public function updateLead(Lead $lead, array $data, ?array $tagIds = null): Lead
    {
        $lead->update($data);

        if ($tagIds !== null) {
            $lead->tags()->sync($tagIds);
        }

        return $lead;
    }

    public function deleteLead(Lead $lead): void
    {
        $lead->delete();
    }

    /**
     * Soft-deletes every given lead id that belongs to $ownerId (silently
     * ignoring ids that don't — same ownership boundary as getLead()) —
     * backs the listing's checkbox-select "Delete Selected" action.
     *
     * @param  int[]  $ids
     * @return int  How many were actually deleted.
     */
    public function deleteMany(array $ids, ?int $ownerId): int
    {
        $leads = Lead::forOwner($ownerId)->whereIn('id', $ids)->get();
        $leads->each(fn (Lead $lead) => $this->deleteLead($lead));

        return $leads->count();
    }

    public function restoreLead(Lead $lead): void
    {
        $lead->restore();
    }

    public function forceDeleteLead(Lead $lead): void
    {
        $lead->forceDelete();
    }
}
