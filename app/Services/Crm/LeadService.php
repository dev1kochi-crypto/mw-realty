<?php

namespace App\Services\Crm;

use App\Models\Lead;
use App\Models\LeadContact;
use App\Models\LeadSource;
use App\Models\LeadStage;
use App\Models\LeadTag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for Lead querying, filtering, statistics, and
 * owner/tag assignment. Reused by the Lead CRUD controller, both dashboards,
 * and the Import/Export classes so none of them hand-roll their own version
 * of these queries — see the module's CLAUDE-facing summary in the PR notes
 * for why this exists as one service rather than duplicated per-caller.
 * Creating a lead (incl. duplicate handling) is LeadCreationService::create().
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
        $query = Lead::with(['owner', 'agent', 'stage', 'source', 'tags'])
            // Team notes only — system activity (stage changes, enquiries…) isn't a "note".
            ->withCount(['notesHistory' => fn ($q) => $q->where('type', \App\Models\LeadNote::TYPE_NOTE)])
            ->forOwner($ownerId)
            ->latest();

        if (!empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    // Also every other email / phone the lead has enquired with.
                    ->orWhereHas('contacts', fn ($c) => $c->where('value', 'like', "%{$term}%"));
            });
        }

        // The listing's "Quick search" box (table_search) — like search, but also matches the
        // other columns the table shows (message, owner, agent, stage, source, tags).
        if (!empty($filters['table_search'])) {
            $term = $filters['table_search'];
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('message', 'like', "%{$term}%")
                    ->orWhereHas('owner', fn ($o) => $o->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('agent', fn ($a) => $a->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('stage', fn ($s) => $s->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('source', fn ($s) => $s->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('tags', fn ($t) => $t->where('lead_tags.name', 'like', "%{$term}%"));
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['owner_id'])) {
            $query->where('portal_user_id', $filters['owner_id']);
        }

        // Assigned agent (agency view). "unassigned" = agency-level leads nobody is working yet.
        if (!empty($filters['agent_id'])) {
            $filters['agent_id'] === 'unassigned'
                ? $query->whereNull('leads.agent_id')
                : $query->where('leads.agent_id', (int) $filters['agent_id']);
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

        if (!empty($filters['quick']) && array_key_exists($filters['quick'], self::QUICK_FILTERS)) {
            [$sql, $bindings] = $this->quickFilterCondition($filters['quick']);
            $query->whereRaw($sql, $bindings);
        }

        return $query;
    }

    /** The stat cards on top of the Leads listing — each one is also a quick filter (`quick`). */
    public const QUICK_FILTERS = [
        'active_24h' => 'Active · 24 hours',
        'last_7d' => 'New · last 7 days',
        'website' => 'With website insights',
    ];

    /**
     * [sql, bindings] for a quick filter, used both to filter the listing and, as a CASE, to
     * count every card in one query (quickFilterCounts):
     *  - active_24h: received in the last 24 hours, or its website visitor was on the site then;
     *  - last_7d:    received in the last 7 days;
     *  - website:    came from a tracked website visitor (has the Insights tab / Lead Insights).
     */
    protected function quickFilterCondition(string $key): array
    {
        $day = now()->subDay();

        return match ($key) {
            'active_24h' => ['(leads.created_at >= ? or exists (select 1 from visitor_leads where visitor_leads.id = leads.visitor_lead_id and visitor_leads.last_seen_at >= ?))', [$day, $day]],
            'last_7d' => ['leads.created_at >= ?', [now()->subDays(7)->startOfDay()]],
            'website' => ['leads.visitor_lead_id is not null', []],
        };
    }

    /**
     * Counts for the stat cards — Total plus each quick filter — under the listing's other filters
     * (search, agent, stage, source, tag, dates), so the cards follow what's being filtered.
     * One aggregate query, whatever the number of leads.
     */
    public function quickFilterCounts(?int $ownerId, array $filters = []): array
    {
        $query = $this->filteredQuery($ownerId, \Illuminate\Support\Arr::except($filters, 'quick'))
            ->setEagerLoads([])->reorder()
            ->select(DB::raw('count(*) as total'));

        foreach (array_keys(self::QUICK_FILTERS) as $key) {
            [$sql, $bindings] = $this->quickFilterCondition($key);
            $query->selectRaw("coalesce(sum(case when {$sql} then 1 else 0 end), 0) as {$key}", $bindings);
        }

        return array_map('intval', (array) $query->toBase()->first());
    }

    /**
     * The leads a listing selection refers to — the one place bulk Delete / Export / Assign
     * Stage / Assign Tag resolve "what's ticked":
     *  - $selectAll: every lead matching the listing's filters (all pages), minus $excludeIds
     *    (rows the user unticked afterwards) — resolved here, never shipped as an id list;
     *  - otherwise: just $ids.
     * Always scoped to what $ownerId can see (forOwner), so foreign ids are silently ignored.
     */
    public function selectedQuery(?int $ownerId, array $filters, bool $selectAll, array $ids = [], array $excludeIds = []): Builder
    {
        if ($selectAll) {
            return $this->filteredQuery($ownerId, $filters)
                ->when($excludeIds, fn ($q) => $q->whereNotIn('leads.id', $excludeIds));
        }

        return Lead::forOwner($ownerId)->whereIn('leads.id', $ids ?: [0])->latest();
    }

    public function getFilteredLeads(?int $ownerId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->filteredQuery($ownerId, $filters)->paginate($perPage)->withQueryString();
    }

    /** Columns the CRM listing can be sorted by (the table's data-column-key values). */
    public const SORTABLE_COLUMNS = ['lead', 'email', 'phone', 'owner', 'agent', 'stage', 'status', 'source', 'message', 'notes', 'received'];

    /**
     * One page of the CRM listing (server-side DataTable) — only the rows on screen are loaded
     * and rendered, so filtering stays fast however many leads there are.
     */
    public function getListingPage(?int $ownerId, array $filters, string $sort = 'received', string $dir = 'desc', int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        $dir = $dir === 'asc' ? 'asc' : 'desc';
        $query = $this->filteredQuery($ownerId, $filters)->reorder();
        $nameOf = fn (string $table, string $foreignKey) => DB::table($table)->select('name')->whereColumn("{$table}.id", "leads.{$foreignKey}")->limit(1);

        match ($sort) {
            'lead' => $query->orderBy('leads.name', $dir),
            'email', 'phone', 'status', 'message' => $query->orderBy("leads.{$sort}", $dir),
            'owner' => $query->orderBy($nameOf('portal_users', 'portal_user_id'), $dir),
            'agent' => $query->orderBy($nameOf('portal_users', 'agent_id'), $dir),
            'stage' => $query->orderBy($nameOf('lead_stages', 'stage_id'), $dir),
            'source' => $query->orderBy($nameOf('lead_sources', 'source_id'), $dir),
            'notes' => $query->orderBy('notes_history_count', $dir),
            default => $query->orderBy('leads.created_at', $dir),
        };

        return $query->orderBy('leads.id', $dir)->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * The single place a Lead is looked up "as owned by" a given scope — the
     * CRUD controller (view/edit/delete/JSON endpoints) is the only caller,
     * so ownership enforcement lives in exactly one query.
     */
    public function getLead(?int $ownerId, $id, bool $withTrashed = false): Lead
    {
        $query = $withTrashed ? Lead::withTrashed() : Lead::with(['property', 'owner', 'agent', 'stage', 'source', 'tags']);

        return $query->forOwner($ownerId)->findOrFail($id);
    }

    /**
     * Like getLead(), but only the owning account (never an agency agent who is merely assigned
     * the lead) — used for delete / restore / force-delete / reassign.
     */
    public function getOwnedLead(?int $ownerId, $id, bool $withTrashed = false): Lead
    {
        $query = $withTrashed ? Lead::withTrashed() : Lead::with(['property', 'owner', 'agent']);

        return $query->ownedBy($ownerId)->findOrFail($id);
    }

    /** Trashed (soft-deleted) leads for this owner, most recently deleted first. */
    public function getTrashedLeads(?int $ownerId, int $perPage = 15): LengthAwarePaginator
    {
        return Lead::onlyTrashed()
            ->with(['property', 'owner', 'stage', 'source'])
            ->ownedBy($ownerId)
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
        $owner = \App\Models\PortalUser::findOrFail($ownerId);

        // A new owner means the old agent assignment no longer applies: an agent owner works it
        // themselves, an agency gets it agency-level unassigned (it decides who works it).
        \Illuminate\Support\Facades\DB::transaction(function () use ($lead, $owner) {
            $previousAgentId = $lead->agent_id;
            $agentId = $owner->isAgent() ? $owner->id : null;
            $type = $owner->isAgent() ? Lead::ASSIGN_PROPERTY_AGENT : Lead::ASSIGN_AGENCY_UNASSIGNED;

            $lead->update([
                'portal_user_id' => $owner->id,
                'agent_id' => $agentId,
                'assignment_type' => $type,
                'assigned_at' => $agentId ? now() : null,
            ]);

            \App\Models\LeadAssignmentHistory::create([
                'lead_id' => $lead->id,
                'agency_id' => $owner->isAgency() ? $owner->id : null,
                'agent_id' => $agentId,
                'previous_agent_id' => $previousAgentId,
                'assignment_type' => $type,
                'assigned_by_type' => \App\Services\Agency\AssignmentActor::current()->type,
                'assigned_by_id' => \App\Services\Agency\AssignmentActor::current()->id,
                'note' => 'Transferred to ' . $owner->displayName(),
                'assigned_at' => now(),
            ]);
        });

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

            // An existing own or global (Super Admin) tag of that name is reused.
            $ids[] = LeadTag::forOwner($ownerId)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->value('id')
                ?? LeadTag::create(['portal_user_id' => $ownerId, 'name' => $name, 'color' => '#14b8a6'])->id;
        }

        return $ids;
    }

    /** @param int[]|null $tagIds Pass null to leave tags untouched. */
    public function updateLead(Lead $lead, array $data, ?array $tagIds = null): Lead
    {
        $lead->update($data);

        if ($tagIds !== null) {
            $this->syncTags($lead, $tagIds);
        }

        return $lead;
    }

    /**
     * Add another email / phone to a lead (a lead can have several). Refused when another lead
     * of the same owner already uses it — that would be the same person twice.
     */
    public function addContact(Lead $lead, string $type, string $value, ?string $countryCode = null): \App\Models\LeadContact
    {
        $value = trim($value);
        $key = $type === \App\Models\LeadContact::TYPE_EMAIL ? \App\Models\LeadContact::emailKey($value) : \App\Models\LeadContact::phoneKey($value);
        $label = $type === \App\Models\LeadContact::TYPE_EMAIL ? 'email' : 'phone number';

        if (!$key) {
            throw \Illuminate\Validation\ValidationException::withMessages(['value' => "Enter a valid {$label}."]);
        }
        if ($lead->contacts()->where('type', $type)->where('match_key', $key)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['value' => "This lead already has that {$label}."]);
        }
        $other = Lead::query()
            ->where('id', '!=', $lead->id)
            ->when($lead->portal_user_id, fn ($q) => $q->where('portal_user_id', $lead->portal_user_id), fn ($q) => $q->whereNull('portal_user_id'))
            ->whereHas('contacts', fn ($q) => $q->where('type', $type)->where('match_key', $key))
            ->first(['id', 'name']);
        if ($other) {
            throw \Illuminate\Validation\ValidationException::withMessages(['value' => "That {$label} already belongs to lead #{$other->id} ({$other->name})."]);
        }

        return DB::transaction(function () use ($lead, $type, $value, $countryCode, $key, $label) {
            $contact = $lead->contacts()->create([
                'type' => $type,
                'value' => $value,
                'phone_country_code' => $type === \App\Models\LeadContact::TYPE_PHONE && !str_starts_with($value, '+') ? $countryCode : null,
                'match_key' => $key,
            ]);

            // First one of its kind becomes the primary contact.
            $primaryField = $type === \App\Models\LeadContact::TYPE_EMAIL ? 'email' : 'phone';
            if (blank($lead->{$primaryField})) {
                $this->setPrimaryContact($lead, $contact, log: false);
            }

            app(LeadNoteService::class)->log($lead, 'details', ucfirst($label) . ' added: ' . $this->contactLabel($contact));

            return $contact;
        });
    }

    /** Make this email / phone the lead's primary one (shown first, used for Call / Email / WhatsApp). */
    public function setPrimaryContact(Lead $lead, \App\Models\LeadContact $contact, bool $log = true): void
    {
        $fields = $contact->type === \App\Models\LeadContact::TYPE_EMAIL
            ? ['email' => $contact->value]
            : ['phone' => $contact->value, 'phone_country_code' => $contact->phone_country_code];

        Lead::withoutActivityLog(fn () => $lead->update($fields));

        if ($log) {
            app(LeadNoteService::class)->log($lead, 'details', 'Primary ' . ($contact->type === 'email' ? 'email' : 'phone') . ' set to ' . $this->contactLabel($contact));
        }
    }

    /**
     * Remove an email / phone. A lead must keep at least one way to reach it; removing the
     * primary promotes the next one of that kind.
     */
    public function removeContact(Lead $lead, \App\Models\LeadContact $contact): void
    {
        if ($lead->contacts()->count() <= 1) {
            throw \Illuminate\Validation\ValidationException::withMessages(['value' => 'A lead needs at least one email or phone number.']);
        }

        DB::transaction(function () use ($lead, $contact) {
            $isEmail = $contact->type === \App\Models\LeadContact::TYPE_EMAIL;
            $primaryKey = $isEmail ? \App\Models\LeadContact::emailKey($lead->email) : \App\Models\LeadContact::phoneKey($lead->phone);
            $contact->delete();

            if ($primaryKey === $contact->match_key) {
                $next = $lead->contacts()->where('type', $contact->type)->orderBy('id')->first();
                if ($next) {
                    $this->setPrimaryContact($lead, $next, log: false);
                } else {
                    Lead::withoutActivityLog(fn () => $lead->update($isEmail ? ['email' => null] : ['phone' => null, 'phone_country_code' => null]));
                }
            }

            app(LeadNoteService::class)->log($lead, 'details', ($isEmail ? 'Email' : 'Phone number') . ' removed: ' . $this->contactLabel($contact));
        });
    }

    private function contactLabel(\App\Models\LeadContact $contact): string
    {
        return trim(($contact->phone_country_code && !str_starts_with($contact->value, '+') ? $contact->phone_country_code . ' ' : '') . $contact->value);
    }

    /** Add tags to a lead, keeping the ones it has (bulk "Add tags"), logging what was new. */
    public function attachTags(Lead $lead, array $tagIds): void
    {
        $attached = $lead->tags()->syncWithoutDetaching($tagIds)['attached'];

        if ($attached) {
            $names = LeadTag::whereIn('id', $attached)->orderBy('name')->pluck('name')->all();
            app(LeadNoteService::class)->log($lead, 'tags', 'Tags added: ' . implode(', ', $names), ['added' => $names, 'removed' => []]);
        }
    }

    /** Replace a lead's tags, logging what was added / removed in its activity history. */
    public function syncTags(Lead $lead, array $tagIds): void
    {
        $result = $lead->tags()->sync($tagIds);
        $names = fn (array $ids) => LeadTag::whereIn('id', $ids)->orderBy('name')->pluck('name')->all();
        $added = $names($result['attached']);
        $removed = $names($result['detached']);

        if ($added || $removed) {
            app(LeadNoteService::class)->log($lead, 'tags', implode("\n", array_filter([
                $added ? 'Tags added: ' . implode(', ', $added) : null,
                $removed ? 'Tags removed: ' . implode(', ', $removed) : null,
            ])), ['added' => $added, 'removed' => $removed]);
        }
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
        $leads = Lead::ownedBy($ownerId)->whereIn('id', $ids)->get();
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

    /**
     * Deleted Leads bulk action — restore or permanently delete the owner's trashed leads:
     * the given ids, or (all = true) every trashed lead minus $excludeIds. Returns the count.
     */
    public function bulkTrashed(?int $ownerId, string $action, bool $all, array $ids, array $excludeIds = []): int
    {
        $query = Lead::onlyTrashed()->ownedBy($ownerId)
            ->when(!$all, fn ($q) => $q->whereIn('id', $ids))
            ->when($all && $excludeIds, fn ($q) => $q->whereNotIn('id', $excludeIds));

        $count = 0;
        // Model by model so restore / forceDelete events (and any cleanup hooked to them) still run.
        $query->chunkById(200, function ($leads) use ($action, &$count) {
            foreach ($leads as $lead) {
                $action === 'restore' ? $this->restoreLead($lead) : $this->forceDeleteLead($lead);
                $count++;
            }
        });

        return $count;
    }
}
