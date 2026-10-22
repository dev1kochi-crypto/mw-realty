<?php

namespace App\Http\Controllers\Crm\Leads;

use App\Exports\LeadsExport;
use App\Http\Controllers\Crm\Concerns\ScopesPortalOwner;
use App\Http\Requests\Crm\StoreLeadRequest;
use App\Http\Requests\Crm\UpdateLeadRequest;
use App\Http\Resources\Crm\LeadDetailResource;
use App\Http\Resources\Crm\LeadResource;
use App\Models\AgencyLeadAssignmentSetting;
use App\Models\Lead;
use App\Models\LeadContact;
use App\Models\LeadImport;
use App\Models\LeadSource;
use App\Models\LeadStage;
use App\Models\LeadTag;
use App\Models\PortalUser;
use App\Models\Property;
use App\Services\Agency\AssignmentActor;
use App\Services\Agency\LeadAssignmentService;
use App\Services\Crm\LeadCreationService;
use App\Services\Crm\LeadDetailService;
use App\Services\Crm\LeadNoteService;
use App\Services\Crm\LeadService;
use App\Services\Crm\LeadTablePreferenceService;
use App\Services\Visitors\VisitorInsights;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @group CRM Leads
 *
 * An agent / company's leads — a Super Admin (web app) sees every account's. Approved accounts
 * only (a pending one gets 403). All querying, statistics and owner rules go through
 * LeadService, so the same rules apply here, on the dashboard, and in the Excel import/export.
 *
 * Selection-based actions (bulk stage / tags / delete, export) take either `ids` (the ticked
 * leads) or `select_all: true` + the listing's filters (+ `exclude_ids` unticked afterwards) =
 * every lead matching the filters, on every page.
 */
class LeadController extends Controller
{
    use ScopesPortalOwner;

    public function __construct(
        private readonly LeadService $leadService,
        private readonly LeadNoteService $leadNoteService,
        private readonly LeadTablePreferenceService $tablePreferences,
    ) {
    }

    /**
     * Leads screen set-up
     *
     * What the Leads screen needs besides the rows: the table fields (`columns.shown` = the
     * viewer's saved order), agency extras (its agents, unassigned count, assignment mode) and a
     * lead import still running. Owner / stage / source / tag filter options page through
     * `/leads/owner-options` and `/leads/options/{type}`.
     */
    public function meta(Request $request)
    {
        if ($owner = $this->owner()) {
            LeadStage::seedDefaultsFor($owner);
            LeadSource::seedDefaultsFor($owner);
        }
        $owner = $this->owner();
        $isAgency = (bool) $owner?->isAgency();

        return response()->json([
            'is_admin' => $this->isAdmin(),
            'is_agency' => $isAgency,
            'columns' => [
                'shown' => $this->tablePreferences->getUserColumns(),
                'available' => collect($this->tablePreferences->orderedAvailableColumns())
                    ->map(fn ($column, $key) => ['key' => $key] + Arr::only($column, ['label', 'group', 'locked', 'default', 'sortable']))->values(),
                'default_order' => array_keys($this->tablePreferences->availableColumns()),
                'groups' => collect(LeadTablePreferenceService::GROUPS)->map(fn ($group, $key) => ['key' => $key] + $group)->values(),
            ],
            'quick_filters' => collect(LeadService::QUICK_FILTERS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'agency' => $isAgency ? [
                'agents' => $owner->eligibleAgentsQuery()->get(['portal_users.id', 'portal_users.name']),
                'unassigned_count' => Lead::where('portal_user_id', $owner->id)->whereNull('agent_id')->count(),
                'assignment' => app(LeadAssignmentService::class)->settingsSummary($owner),
            ] : null,
            'import' => $this->importForBanner($request)?->toProgress(),
        ]);
    }

    /**
     * List leads
     *
     * One page of the listing plus the stat-card counts (`quick_counts`, which ignore `q`).
     *
     * @queryParam search string Name, email or phone (also every other email / phone it enquired with). Example: sara
     * @queryParam q string The table's quick search. Example: marina
     * @queryParam stage_id integer Example: 3
     * @queryParam source_id integer Example: 2
     * @queryParam tag_id integer Example: 5
     * @queryParam status string active or inactive. Example: active
     * @queryParam agent_id integer Agency only — leads assigned to this agent (`unassigned` = none). Example: 14
     * @queryParam owner_id integer Super Admin only — one account's leads. Example: 12
     * @queryParam date_from string Received on/after (Y-m-d). Example: 2026-01-01
     * @queryParam date_to string Received on/before (Y-m-d). Example: 2026-01-31
     * @queryParam quick string active_24h, last_7d or website. Example: last_7d
     * @queryParam sort string A column key (see Leads screen set-up). Example: received
     * @queryParam dir string asc or desc. Example: desc
     * @queryParam per_page integer 10, 25, 50 or 100. Example: 25
     * @queryParam page integer Example: 1
     */
    public function index(Request $request)
    {
        $ownerId = $this->ownerId();
        $filters = $this->filtersFromRequest($request);
        $perPage = (int) $request->input('per_page', 25);

        $leads = $this->leadService->getListingPage(
            $ownerId,
            $filters,
            in_array($request->input('sort'), LeadService::SORTABLE_COLUMNS, true) ? $request->input('sort') : 'received',
            $request->input('dir') === 'asc' ? 'asc' : 'desc',
            in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25,
            max(1, (int) $request->input('page', 1)),
            $this->tablePreferences->getUserColumns(),
        );

        return LeadResource::collection($leads)->additional([
            // Matching leads before the quick search — "filtered from N".
            'unfiltered_total' => empty($filters['table_search'])
                ? $leads->total()
                : $this->leadService->filteredQuery($ownerId, Arr::except($filters, 'table_search'))->setEagerLoads([])->reorder()->count(),
            'quick_counts' => $this->leadService->quickFilterCounts($ownerId, Arr::except($filters, 'table_search')),
        ]);
    }

    /**
     * Save table fields
     *
     * The viewer's shown fields, in order (Lead is always first; locked fields are kept).
     *
     * @bodyParam columns string[] Example: ["lead", "phone", "stage", "tags", "received"]
     *
     * @response 200 {"success": true, "columns": ["lead", "phone", "stage", "tags", "received"], "message": "Table fields saved."}
     */
    public function updateTableColumns(Request $request)
    {
        $validated = $request->validate([
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string', 'max:30'],
        ]);

        return response()->json([
            'success' => true,
            'columns' => $this->tablePreferences->saveUserColumns($validated['columns'] ?? []),
            'message' => 'Table fields saved.',
        ]);
    }

    /**
     * Create a lead
     *
     * Same email / phone as one of your existing leads → that lead is updated instead
     * (`merged: true`), or restored from Deleted Leads. Either `email` or `phone` is required.
     *
     * @bodyParam name string required Example: John Smith
     * @bodyParam email string Example: john@example.com
     * @bodyParam phone string Example: 501234567
     * @bodyParam phone_country_code string Example: +971
     * @bodyParam message string Example: Looking for a 2-bed in Marina.
     * @bodyParam status string active or inactive. Example: active
     * @bodyParam stage_id integer Example: 3
     * @bodyParam source_id integer Example: 2
     * @bodyParam tags integer[] Tag ids. Example: [5]
     * @bodyParam owner_id integer Super Admin only — the account the lead belongs to. Example: 12
     *
     * @response 200 {"success": true, "message": "Lead created.", "id": 101, "merged": false}
     */
    public function store(StoreLeadRequest $request)
    {
        $ownerId = $this->effectiveOwnerId();
        abort_if(!$ownerId, 403);

        if ($this->isAdmin() && $request->filled('owner_id')) {
            $ownerId = (int) $request->input('owner_id');
        }

        $lead = app(LeadCreationService::class)->create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'phone_country_code' => $request->filled('phone') ? $request->input('phone_country_code') : null,
            'message' => $request->input('message'),
            'page_source' => 'manual',
            'status' => $request->input('status', 'active'),
            'stage_id' => $request->input('stage_id') ?: null,
            'source_id' => $request->input('source_id') ?: null,
        ],
            ownerId: $ownerId,
            autoAssign: false,
            tagIds: $request->input('tags', []),
            notify: false,
            noteAuthor: $this->actorName(),
        );

        return response()->json([
            'success' => true,
            'message' => match (true) {
                $lead->wasRestored => 'This contact was in Deleted Leads as "' . $lead->name . '" — that lead was restored and updated instead of creating a duplicate.',
                $lead->wasMerged => 'This contact already exists as "' . $lead->name . '" — the existing lead was updated instead of creating a duplicate.',
                default => 'Lead created.',
            },
            'id' => $lead->id,
            'merged' => $lead->wasMerged,
        ]);
    }

    /**
     * Show a lead
     *
     * Everything on the lead screen: the lead, its emails / phones, enquiry details, activity
     * timeline, every enquiry it made (`source_history`), notes, purchases, assignment, the
     * stage / source / tag options it can be edited with, and the previous / next lead in your
     * list. Website activity loads separately (`GET /leads/{id}/insights/summary`).
     */
    public function show($id)
    {
        return new LeadDetailResource($this->detailPageData($this->findOwned($id)));
    }

    /**
     * Website activity summary
     *
     * The Insights tab — what this lead did on the website: stats, engagement level, most
     * interested properties, what they search for, and the first page of the activity timeline,
     * favorites, saved searches and AI chats. 404 when the lead didn't come from a tracked visitor.
     */
    public function insightsSummary($id)
    {
        $visitorLead = $this->findOwned($id)->visitorLead;
        abort_unless($visitorLead, 404);

        return response()->json(app(VisitorInsights::class)->summaryJson($visitorLead));
    }

    /**
     * Website activity — next page
     *
     * The next page of one Insights list (load on scroll).
     *
     * @queryParam section string required timeline, favorites, searches, chats or messages. Example: timeline
     * @queryParam after integer The `next` cursor from the previous page. Example: 5512
     * @queryParam filter string Timeline only: key (default) or all (adds page views). Example: key
     * @queryParam conversation integer messages only: the chat's id. Example: 31
     *
     * @response 200 {"items": [], "next": null}
     */
    public function insights(Request $request, $id)
    {
        $visitorLead = $this->findOwned($id)->visitorLead;
        abort_unless($visitorLead, 404);

        return response()->json(app(VisitorInsights::class)->feedJson($visitorLead, $request));
    }

    /**
     * Edit lead fields
     *
     * Saves only the fields sent (one card at a time on the lead screen). `owner_id` (transfer
     * to another account) is Super Admin only.
     *
     * @bodyParam name string Example: John Smith
     * @bodyParam company string Example: Acme LLC
     * @bodyParam country string Example: UAE
     * @bodyParam message string Example: Prefers sea view.
     * @bodyParam status string active or inactive. Example: inactive
     * @bodyParam owner_id integer Super Admin only. Example: 12
     *
     * @response 200 {"success": true, "message": "Name updated."}
     */
    public function updateFields(Request $request, $id)
    {
        $lead = $this->findOwned($id);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'message' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'owner_id' => ['required', 'integer', 'exists:portal_users,id'],
        ];
        $sent = array_intersect_key($rules, $request->all());
        abort_if(!$sent, 422, 'Nothing to update.');
        abort_if(isset($sent['owner_id']) && !$this->isAdmin(), 403);

        $data = $request->validate($sent);

        $message = match (true) {
            isset($data['status']) => 'Lead marked as ' . ucfirst($data['status']) . '.',
            array_key_exists('owner_id', $data) => 'Lead transferred.',
            isset($data['name']) => 'Name updated.',
            default => 'Lead details updated.',
        };

        if (array_key_exists('owner_id', $data)) {
            if ((int) $data['owner_id'] !== $lead->portal_user_id) {
                $this->leadService->assignOwner($lead, (int) $data['owner_id']);
            }
            unset($data['owner_id']);
        }
        if ($data) {
            $lead->update($data);
        }

        return response()->json(['success' => true, 'message' => $message]);
    }

    /**
     * Add an email / phone
     *
     * @bodyParam type string required email or phone. Example: phone
     * @bodyParam value string required Example: 501234567
     * @bodyParam phone_country_code string With a phone. Example: +971
     *
     * @response 200 {"success": true, "message": "Phone number added."}
     */
    public function addContact(Request $request, $id)
    {
        $lead = $this->findOwned($id);
        $data = $request->validate([
            'type' => ['required', Rule::in([LeadContact::TYPE_EMAIL, LeadContact::TYPE_PHONE])],
            'value' => $request->input('type') === 'email' ? \App\Rules\PhoneNumber::emailRules() : \App\Rules\PhoneNumber::rules(true),
            'phone_country_code' => \App\Rules\PhoneNumber::countryCodeRules(),
        ]);

        $this->leadService->addContact($lead, $data['type'], $data['value'], $data['phone_country_code'] ?? null);

        return response()->json(['success' => true, 'message' => ($data['type'] === 'email' ? 'Email' : 'Phone number') . ' added.']);
    }

    /**
     * Make an email / phone primary
     *
     * @response 200 {"success": true, "message": "Primary contact updated."}
     */
    public function setPrimaryContact($id, $contactId)
    {
        $lead = $this->findOwned($id);
        $this->leadService->setPrimaryContact($lead, $lead->contacts()->findOrFail($contactId));

        return response()->json(['success' => true, 'message' => 'Primary contact updated.']);
    }

    /**
     * Remove an email / phone
     *
     * @response 200 {"success": true, "message": "Removed."}
     */
    public function removeContact($id, $contactId)
    {
        $lead = $this->findOwned($id);
        $this->leadService->removeContact($lead, $lead->contacts()->findOrFail($contactId));

        return response()->json(['success' => true, 'message' => 'Removed.']);
    }

    /**
     * Update a lead
     *
     * The whole lead at once (the listing's Edit form). The source is fixed once a lead exists.
     *
     * @bodyParam name string required Example: John Smith
     * @bodyParam email string Example: john@example.com
     * @bodyParam phone string Example: 501234567
     * @bodyParam phone_country_code string Example: +971
     * @bodyParam message string Example: Looking for a 2-bed in Marina.
     * @bodyParam status string required active or inactive. Example: active
     * @bodyParam stage_id integer Example: 3
     * @bodyParam tags integer[] Replaces the lead's tags. Example: [5, 6]
     * @bodyParam owner_id integer Super Admin only — transfer. Example: 12
     *
     * @response 200 {"success": true, "message": "Lead updated.", "id": 101}
     */
    public function update(UpdateLeadRequest $request, $id)
    {
        $lead = $this->findOwned($id);

        $this->leadService->updateLead($lead, [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'phone_country_code' => $request->filled('phone') ? $request->input('phone_country_code') : null,
            'message' => $request->input('message'),
            'status' => $request->input('status'),
            'stage_id' => $request->input('stage_id') ?: $lead->stage_id, // a lead always has a stage
            'source_id' => $lead->source_id, // fixed once the lead exists — not editable
        ], $request->input('tags', []));

        if ($this->isAdmin() && $request->filled('owner_id') && (int) $request->input('owner_id') !== $lead->portal_user_id) {
            $this->leadService->assignOwner($lead, (int) $request->input('owner_id'));
        }

        return response()->json(['success' => true, 'message' => 'Lead updated.', 'id' => $lead->id]);
    }

    /**
     * Change stage
     *
     * @bodyParam stage_id integer Example: 4
     *
     * @response 200 {"success": true, "message": "Stage updated.", "stage": {"id": 4, "name": "Viewing", "color": "#0ea5e9"}}
     */
    public function updateStage(Request $request, $id)
    {
        $lead = $this->findOwned($id);

        $request->validate([
            'stage_id' => ['nullable', 'integer', Rule::exists('lead_stages', 'id')->where($this->leadOrViewerItems($lead))],
        ]);

        $lead->update(['stage_id' => $request->input('stage_id') ?: null]);
        $stage = $lead->stage()->first();

        return response()->json([
            'success' => true,
            'message' => 'Stage updated.',
            'stage' => $stage ? ['id' => $stage->id, 'name' => $stage->name, 'color' => $stage->color] : null,
        ]);
    }

    /**
     * Replace tags
     *
     * @bodyParam tags integer[] Example: [5, 6]
     *
     * @response 200 {"success": true, "message": "Tags updated.", "tags": [{"id": 5, "name": "VIP", "color": "#ef4444"}]}
     */
    public function syncTags(Request $request, $id)
    {
        $lead = $this->findOwned($id);

        $request->validate([
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', Rule::exists('lead_tags', 'id')->where($this->leadOrViewerItems($lead))],
        ]);

        $this->leadService->syncTags($lead, $request->input('tags') ?: []);

        return response()->json([
            'success' => true,
            'message' => 'Tags updated.',
            'tags' => $lead->tags()->get()->map(fn ($tag) => ['id' => $tag->id, 'name' => $tag->name, 'color' => $tag->color]),
        ]);
    }

    /**
     * Delete a lead
     *
     * Moves it to Deleted Leads. Only the owning account can delete — an agency agent who is
     * just assigned the lead gets 404.
     *
     * @response 200 {"success": true, "message": "Lead deleted."}
     */
    public function destroy($id)
    {
        $this->leadService->deleteLead($this->findStrictlyOwned($id));

        return response()->json(['success' => true, 'message' => 'Lead deleted.']);
    }

    /**
     * Stage / source / tag options
     *
     * Your own items plus the shared ones, searched and paged on the server (20 per page —
     * load the `next_page` on scroll).
     *
     * @urlParam type string required stages, sources or tags. Example: stages
     * @queryParam search string Example: view
     * @queryParam page integer Example: 1
     *
     * @response 200 {"data": [{"id": 3, "name": "New", "color": "#64748b", "leads": 12}], "next_page": null, "total": 1}
     */
    public function masterOptions(Request $request, string $type)
    {
        $ownerId = $this->effectiveOwnerId();
        $search = trim((string) $request->input('search'));

        if ($owner = $this->owner()) {
            $type === 'sources' ? LeadSource::seedDefaultsFor($owner) : LeadStage::seedDefaultsFor($owner);
        }

        $query = match ($type) {
            'stages' => LeadStage::forOwner($ownerId)->orderBy('order_index'),
            'sources' => LeadSource::forOwner($ownerId)->orderBy('order_index'),
            default => LeadTag::forOwner($ownerId)->orderBy('name'),
        };

        $page = $query->withCount('leads')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%' . $search . '%'))
            ->paginate(20, ['*'], 'page', max(1, (int) $request->input('page', 1)));

        return response()->json([
            'data' => collect($page->items())->map(fn ($row) => [
                'id' => $row->id, 'name' => $row->name, 'color' => $row->color, 'leads' => (int) $row->leads_count,
            ]),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
            'total' => $page->total(),
        ]);
    }

    /**
     * Account options
     *
     * Super Admin only — accounts to filter by or transfer a lead to, searched and paged on the server.
     *
     * @queryParam search string Example: acme
     * @queryParam page integer Example: 1
     *
     * @response 200 {"data": [{"id": 12, "name": "Acme Realty", "color": null}], "next_page": 2, "total": 230}
     */
    public function ownerOptions(Request $request)
    {
        abort_unless($this->isAdmin(), 403);

        $search = trim((string) $request->input('search'));
        $page = PortalUser::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
                ->orWhere('company_name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(20, ['id', 'name', 'company_name', 'type'], 'page', max(1, (int) $request->input('page', 1)));

        return response()->json([
            'data' => collect($page->items())->map(fn ($user) => ['id' => $user->id, 'name' => $user->displayName(), 'color' => null]),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
            'total' => $page->total(),
        ]);
    }

    /**
     * Bulk: change stage
     *
     * One save per lead, so each lead's timeline records it.
     *
     * @bodyParam stage_id integer Example: 4
     * @bodyParam ids integer[] The ticked leads. Example: [101, 102]
     * @bodyParam select_all boolean Every lead matching the listing filters (sent alongside). Example: false
     * @bodyParam exclude_ids integer[] With select_all, leads unticked afterwards. Example: []
     */
    public function bulkStage(Request $request)
    {
        $data = $request->validate([
            'stage_id' => ['nullable', 'integer', Rule::exists('lead_stages', 'id')->whereIn('portal_user_id', LeadStage::usableOwnerIds($this->effectiveOwnerId()))],
        ], ['stage_id.exists' => 'That stage does not belong to your account.']);
        $ids = $this->selectedLeadsQuery($request)->pluck('leads.id');

        $changed = 0;
        $total = $ids->count();
        DB::transaction(function () use ($ids, $data, &$changed) {
            Lead::whereIn('id', $ids)->chunkById(200, function ($leads) use ($data, &$changed) {
                foreach ($leads as $lead) {
                    if ((int) $lead->stage_id !== (int) ($data['stage_id'] ?? 0)) {
                        $lead->update(['stage_id' => $data['stage_id'] ?? null]);
                        $changed++;
                    }
                }
            });
        });

        $stage = isset($data['stage_id']) ? LeadStage::find($data['stage_id'])?->name : 'No stage';

        return response()->json([
            'success' => true,
            'message' => "Stage \"{$stage}\" set on {$changed} " . ($changed === 1 ? 'lead' : 'leads') . ($changed < $total ? ' (' . ($total - $changed) . ' already had it)' : '') . '.',
        ]);
    }

    /**
     * Bulk: add tags
     *
     * Every tag is added to every selected lead (existing tags are kept).
     *
     * @bodyParam tags integer[] required Example: [5]
     * @bodyParam ids integer[] Example: [101, 102]
     * @bodyParam select_all boolean Example: false
     * @bodyParam exclude_ids integer[] Example: []
     */
    public function bulkTags(Request $request)
    {
        $data = $request->validate([
            'tags' => ['required', 'array', 'min:1'],
            'tags.*' => ['integer', Rule::exists('lead_tags', 'id')->whereIn('portal_user_id', LeadStage::usableOwnerIds($this->effectiveOwnerId()))],
        ], ['tags.required' => 'Choose at least one tag.', 'tags.*.exists' => 'One of the tags does not belong to your account.']);
        $ids = $this->selectedLeadsQuery($request)->pluck('leads.id');

        DB::transaction(function () use ($ids, $data) {
            Lead::whereIn('id', $ids)->chunkById(200, function ($leads) use ($data) {
                $leads->each(fn (Lead $lead) => $this->leadService->attachTags($lead, $data['tags']));
            });
        });

        $tagCount = count($data['tags']);

        return response()->json([
            'success' => true,
            'message' => ($tagCount === 1 ? '1 tag' : "{$tagCount} tags") . ' added to ' . $ids->count() . ' ' . ($ids->count() === 1 ? 'lead' : 'leads') . '.',
        ]);
    }

    /**
     * Bulk: delete
     *
     * Moves the selected leads (only ones you own) to Deleted Leads.
     *
     * @bodyParam ids integer[] Example: [101, 102]
     * @bodyParam select_all boolean Example: false
     * @bodyParam exclude_ids integer[] Example: []
     *
     * @response 200 {"success": true, "message": "2 leads deleted.", "count": 2}
     */
    public function bulkDestroy(Request $request)
    {
        $ids = $this->selectedLeadsQuery($request)->pluck('leads.id')->all();
        abort_if(!$ids, 422, 'No leads selected.');

        $count = $this->leadService->deleteMany($ids, $this->ownerId());

        return response()->json(['success' => true, 'message' => $count === 1 ? '1 lead deleted.' : "{$count} leads deleted.", 'count' => $count]);
    }

    /**
     * Export to Excel
     *
     * An .xlsx of the selection (`ids` / `select_all` + filters), or — with neither — every lead
     * matching the filters.
     *
     * @bodyParam ids integer[] Example: [101, 102]
     * @bodyParam select_all boolean Example: true
     * @bodyParam exclude_ids integer[] Example: []
     */
    public function export(Request $request)
    {
        $query = $request->has('ids') || $request->boolean('select_all')
            ? $this->selectedLeadsQuery($request)
            : $this->leadService->filteredQuery($this->ownerId(), $this->filtersFromRequest($request));

        return Excel::download(new LeadsExport($query), 'leads-' . now()->format('Y-m-d-His') . '.xlsx');
    }

    /**
     * Deleted leads
     *
     * Leads you deleted, most recently deleted first.
     *
     * @queryParam page integer Example: 1
     */
    public function trashed()
    {
        $leads = $this->leadService->getTrashedLeads($this->ownerId(), 25);
        $leads->getCollection()->load(['tags', 'agent']);

        return LeadResource::collection($leads)->additional(['is_admin' => $this->isAdmin()]);
    }

    /**
     * Restore a deleted lead
     *
     * @response 200 {"success": true, "message": "Lead restored."}
     */
    public function restore($id)
    {
        $this->leadService->restoreLead($this->findStrictlyOwned($id, withTrashed: true));

        return response()->json(['success' => true, 'message' => 'Lead restored.']);
    }

    /**
     * Delete a lead permanently
     *
     * @response 200 {"success": true, "message": "Lead permanently deleted."}
     */
    public function forceDestroy($id)
    {
        $this->leadService->forceDeleteLead($this->findStrictlyOwned($id, withTrashed: true));

        return response()->json(['success' => true, 'message' => 'Lead permanently deleted.']);
    }

    /**
     * Bulk restore / delete permanently
     *
     * @bodyParam action string required restore or force. Example: restore
     * @bodyParam ids integer[] Example: [101, 102]
     * @bodyParam select_all boolean Every deleted lead. Example: false
     * @bodyParam exclude_ids integer[] Example: []
     *
     * @response 200 {"success": true, "message": "2 leads restored."}
     */
    public function bulkTrashed(Request $request)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['restore', 'force'])],
            'select_all' => ['nullable', 'boolean'],
            'ids' => ['required_unless:select_all,1', 'array'],
            'ids.*' => ['integer'],
            'exclude_ids' => ['nullable', 'array'],
            'exclude_ids.*' => ['integer'],
        ], ['ids.required_unless' => 'Select at least one lead.']);

        $count = $this->leadService->bulkTrashed(
            $this->ownerId(),
            $data['action'],
            $request->boolean('select_all'),
            array_map('intval', $data['ids'] ?? []),
            array_map('intval', $data['exclude_ids'] ?? []),
        );
        $noun = $count === 1 ? '1 lead' : "{$count} leads";

        return response()->json(['success' => true, 'message' => $data['action'] === 'restore' ? "{$noun} restored." : "{$noun} permanently deleted."]);
    }

    /**
     * Assign to an agent
     *
     * Agency leads only — the owning agency (or Super Admin). `agent_id: null` moves the lead
     * back to agency level. Options come from the lead's `assignment.agent_options`.
     *
     * @bodyParam agent_id integer Example: 14
     * @bodyParam note string Example: Speaks Arabic.
     *
     * @response 200 {"success": true, "message": "Lead assigned to Omar.", "agent_id": 14}
     */
    public function assign(Request $request, LeadAssignmentService $assignment, $id)
    {
        $lead = $this->isAdmin() ? Lead::with('owner')->findOrFail($id) : $this->findStrictlyOwned($id);
        abort_unless($this->canAssign($lead), 403);

        $data = $request->validate([
            'agent_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $assignment->assignManually($lead, isset($data['agent_id']) ? (int) $data['agent_id'] : null, AssignmentActor::current(), $data['note'] ?? null);
        $lead->load('agent');

        return response()->json([
            'success' => true,
            'message' => $lead->agent ? "Lead assigned to {$lead->agent->name}." : 'Lead moved back to agency level.',
            'agent_id' => $lead->agent_id,
        ]);
    }

    /**
     * Distribute unassigned leads
     *
     * Agency only — round-robins every unassigned agency lead across its active agents.
     *
     * @response 200 {"success": true, "message": "4 unassigned leads distributed.", "count": 4}
     */
    public function distributeUnassigned(LeadAssignmentService $assignment)
    {
        $agency = $this->owner();
        abort_unless($agency?->isAgency(), 403);

        $count = $assignment->distributeUnassigned($agency, AssignmentActor::portal($agency));

        return response()->json([
            'success' => true,
            'message' => $count ? "{$count} unassigned " . ($count === 1 ? 'lead' : 'leads') . ' distributed.' : 'No active agents to distribute to.',
            'count' => $count,
        ]);
    }

    /**
     * Lead assignment settings
     *
     * Agency only — how new agency leads reach its agents: round robin (`automatic`) for the
     * chosen kinds of lead, or `manual`.
     *
     * @response 200 {"mode": "automatic", "sources": ["property", "generic"], "available_sources": [{"key": "property", "label": "Property enquiries"}], "agent_count": 3}
     */
    public function assignmentSettings()
    {
        $agency = $this->owner();
        abort_unless($agency?->isAgency(), 403);

        return response()->json(app(LeadAssignmentService::class)->settingsSummary($agency));
    }

    /**
     * Update lead assignment settings
     *
     * @bodyParam mode string required automatic or manual. Example: automatic
     * @bodyParam sources string[] With automatic: property, generic and/or facebook. Example: ["property", "generic"]
     *
     * @response 200 {"success": true, "message": "Round robin updated.", "settings": {"mode": "automatic"}}
     */
    public function updateAssignmentSettings(Request $request, LeadAssignmentService $assignment)
    {
        $agency = $this->owner();
        abort_unless($agency?->isAgency(), 403);

        $data = $request->validate([
            'mode' => ['required', Rule::in([AgencyLeadAssignmentSetting::MODE_AUTOMATIC, AgencyLeadAssignmentSetting::MODE_MANUAL])],
            'sources' => ['array'],
            'sources.*' => [Rule::in(array_keys(AgencyLeadAssignmentSetting::SOURCES))],
        ]);

        if ($error = $assignment->updateSettings($agency, $data['mode'], $data['sources'] ?? [])) {
            return response()->json(['message' => $error, 'errors' => ['sources' => [$error]]], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $data['mode'] === AgencyLeadAssignmentSetting::MODE_MANUAL
                ? 'Manual assignment on — new leads wait in Unassigned for you to assign.'
                : 'Round robin updated.',
            'settings' => $assignment->settingsSummary($agency->fresh()),
        ]);
    }

    /** The filter set shared by the listing, export, and selection-based bulk actions. */
    private function filtersFromRequest(Request $request): array
    {
        return array_filter([
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'owner_id' => $request->input('owner_id'),
            'agent_id' => $request->input('agent_id'),
            'stage_id' => $request->input('stage_id'),
            'source_id' => $request->input('source_id'),
            'tag_id' => $request->input('tag_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'table_search' => is_string($request->input('q')) ? trim($request->input('q')) : null,
            'quick' => array_key_exists((string) $request->input('quick'), LeadService::QUICK_FILTERS) ? $request->input('quick') : null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /** The listing's current selection: ticked `ids`, or `select_all` (+ filters, minus `exclude_ids`). */
    private function selectedLeadsQuery(Request $request): Builder
    {
        $request->validate([
            'select_all' => ['nullable', 'boolean'],
            'ids' => ['required_unless:select_all,1', 'array', 'max:1000'],
            'ids.*' => ['integer'],
            'exclude_ids' => ['nullable', 'array'],
            'exclude_ids.*' => ['integer'],
        ], ['ids.required_unless' => 'Select at least one lead.']);

        return $this->leadService->selectedQuery(
            $this->ownerId(),
            $this->filtersFromRequest($request),
            $request->boolean('select_all'),
            array_map('intval', (array) $request->input('ids', [])),
            array_map('intval', (array) $request->input('exclude_ids', [])),
        );
    }

    private function findOwned($id, bool $withTrashed = false): Lead
    {
        return $this->leadService->getLead($this->ownerId(), $id, $withTrashed);
    }

    /** Owning account only — an agency agent can work an assigned agency lead but not delete/restore it. */
    private function findStrictlyOwned($id, bool $withTrashed = false): Lead
    {
        return $this->leadService->getOwnedLead($this->ownerId(), $id, $withTrashed);
    }

    /** The owning agency (or Super Admin) may hand an agency lead to one of that agency's agents. */
    private function canAssign(Lead $lead): bool
    {
        return ($this->isAdmin() || ($this->ownerId() && $lead->portal_user_id === $this->ownerId()))
            && $lead->owner?->isAgency();
    }

    /** Stage / tag rows usable on this lead: its owner's, the viewer's own, and MW Realty's shared ones. */
    private function leadOrViewerItems(Lead $lead): \Closure
    {
        $viewerOwnerId = $this->effectiveOwnerId();

        return function ($query) use ($lead, $viewerOwnerId) {
            $query->where('portal_user_id', $lead->portal_user_id);
            if ($viewerOwnerId && $viewerOwnerId !== $lead->portal_user_id) {
                $query->orWhere('portal_user_id', $viewerOwnerId);
            }
            $query->orWhere('portal_user_id', LeadStage::globalOwnerId());
        };
    }

    /**
     * The lead detail screen's data — the viewer's own master data (with the lead's current
     * values kept in the lists even when they belong to another owner), plus the previous /
     * next lead in the viewer's list order (newest first).
     */
    private function detailPageData(Lead $lead): array
    {
        $lead->load(['property', 'owner', 'agent', 'stage', 'source', 'tags', 'contacts', 'notesHistory']);
        $details = app(LeadDetailService::class);
        $masterDataOwnerId = $this->effectiveOwnerId();

        $withCurrent = fn ($list, $current) => $current && !$list->contains('id', $current->id) ? $list->push($current) : $list;
        $stages = $withCurrent(LeadStage::forOwner($masterDataOwnerId)->orderBy('order_index')->get(), $lead->stage);
        $sources = $withCurrent(LeadSource::forOwner($masterDataOwnerId)->orderBy('order_index')->get(), $lead->source);
        $tags = LeadTag::forOwner($masterDataOwnerId)->orderBy('name')->get();
        $tags = $tags->concat($lead->tags->reject(fn ($t) => $tags->contains('id', $t->id)))->values();

        $canAssign = $this->canAssign($lead);
        $visible = fn () => Lead::forOwner($this->ownerId())->whereKeyNot($lead->id);
        $newer = $visible()->where(fn ($q) => $q->where('created_at', '>', $lead->created_at)
            ->orWhere(fn ($same) => $same->where('created_at', $lead->created_at)->where('id', '>', $lead->id)))
            ->orderBy('created_at')->orderBy('id')->value('id');
        $older = $visible()->where(fn ($q) => $q->where('created_at', '<', $lead->created_at)
            ->orWhere(fn ($same) => $same->where('created_at', $lead->created_at)->where('id', '<', $lead->id)))
            ->orderByDesc('created_at')->orderByDesc('id')->value('id');

        return [
            'lead' => $lead,
            'previousLeadId' => $newer,
            'nextLeadId' => $older,
            'contacts' => $details->contactList($lead),
            'enquiryDetails' => $details->enquiryDetails($lead),
            'timeline' => $details->timeline($lead),
            'notes' => $lead->notesHistory->where('type', \App\Models\LeadNote::TYPE_NOTE)->sortByDesc('id')->values(),
            'purchases' => Property::where('sold_lead_id', $lead->id)->orderByDesc('sold_at')
                ->get(['id', 'translations', 'reference_no', 'segment', 'currency', 'price', 'sold_at', 'sold_type', 'sold_price', 'rented_until']),
            'sourceHistory' => $details->sourceHistory($lead),
            'hasInsights' => $lead->visitor_lead_id !== null,
            'stages' => $stages,
            'sources' => $sources,
            'tags' => $tags,
            'isAdmin' => $this->isAdmin(),
            'canAssign' => $canAssign,
            'agentOptions' => $canAssign ? $lead->owner->eligibleAgentsQuery()->get(['portal_users.id', 'portal_users.name']) : collect(),
            'assignmentHistory' => $lead->assignmentHistory()->with('agent:id,name')->get(),
            'canDelete' => $this->isAdmin() || $lead->portal_user_id === $this->ownerId(),
        ];
    }

    /** The import to show on the Leads screen: the one a notification links to (`import`), else one still running. */
    private function importForBanner(Request $request): ?LeadImport
    {
        $ownerId = $this->effectiveOwnerId();
        if (!$ownerId) {
            return null;
        }
        $query = LeadImport::where('portal_user_id', $ownerId);

        $import = $request->filled('import')
            ? (clone $query)->find((int) $request->input('import'))
            : (clone $query)->whereIn('status', [LeadImport::STATUS_QUEUED, LeadImport::STATUS_RUNNING])->latest('id')->first();

        return $import?->failIfStale();
    }
}
