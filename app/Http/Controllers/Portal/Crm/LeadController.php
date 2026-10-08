<?php

namespace App\Http\Controllers\Portal\Crm;

use App\Exports\LeadsExport;
use App\Exports\FacebookLeadsImportSampleExport;
use App\Exports\LeadsImportTemplateExport;
use App\Http\Controllers\Portal\Crm\Concerns\ScopesPortalOwner;
use App\Http\Requests\Crm\StoreLeadRequest;
use App\Http\Requests\Crm\UpdateLeadRequest;
use App\Imports\LeadImportReader;
use App\Imports\LeadsImport;
use App\Jobs\ProcessLeadImport;
use App\Models\Lead;
use App\Models\LeadImport;
use App\Models\LeadSource;
use App\Models\LeadStage;
use App\Models\LeadTag;
use App\Models\PortalUser;
use App\Services\Agency\AssignmentActor;
use App\Services\Agency\LeadAssignmentService;
use App\Services\Crm\LeadCreationService;
use App\Services\Crm\LeadNoteService;
use App\Services\Crm\LeadService;
use App\Services\Crm\LeadTablePreferenceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

/**
 * The portal-side Leads list/detail/CRUD — Super Admin (cms guard) sees every
 * lead, an Agent/Company (portal guard) sees and can only act on their own.
 * All querying/statistics/owner-assignment goes through LeadService so the
 * same rules apply here, on the dashboards, and in the Excel import/export.
 */
class LeadController extends Controller
{
    use ScopesPortalOwner;

    public function __construct(
        private readonly LeadService $leadService,
        private readonly LeadNoteService $leadNoteService,
        private readonly LeadTablePreferenceService $leadTablePreferenceService,
    ) {
    }

    /** The filter set shared by the listing, export, and (for master-data dropdowns) the create/edit forms. */
    protected function filtersFromRequest(Request $request): array
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
            // The table's "Quick search" box (server-side DataTable).
            'table_search' => is_string($request->input('q')) ? trim($request->input('q')) : null,
            // A stat card on top of the listing (LeadService::QUICK_FILTERS).
            'quick' => array_key_exists((string) $request->input('quick'), LeadService::QUICK_FILTERS) ? $request->input('quick') : null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    public function index(Request $request)
    {
        $ownerId = $this->ownerId();

        if ($owner = $this->owner()) {
            LeadStage::seedDefaultsFor($owner);
            LeadSource::seedDefaultsFor($owner);
        }

        $filters = $this->filtersFromRequest($request);

        // Only the page on screen is loaded and rendered (server-side DataTable) — rendering every
        // matching lead on each filter change is what made filtering slow.
        $perPage = (int) $request->input('per_page', 10);
        $listing = [
            'sort' => in_array($request->input('sort'), LeadService::SORTABLE_COLUMNS, true) ? $request->input('sort') : 'received',
            'dir' => $request->input('dir') === 'asc' ? 'asc' : 'desc',
            'per_page' => in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10,
            'q' => $filters['table_search'] ?? '',
        ];
        $leadTableColumns = $this->leadTablePreferenceService->getUserColumns();
        $leads = $this->leadService->getListingPage($ownerId, $filters, $listing['sort'], $listing['dir'], $listing['per_page'], max(1, (int) $request->input('page', 1)), $leadTableColumns);
        // Matching leads before the Quick search — DataTables' "filtered from N" total.
        $listingTotal = empty($filters['table_search'])
            ? $leads->total()
            : $this->leadService->filteredQuery($ownerId, \Illuminate\Support\Arr::except($filters, 'table_search'))->setEagerLoads([])->reorder()->count();

        // In the Super Admin's global view, use the same shared Admin master
        // data managed under Master > Stage, Tag, and Source. Selecting an
        // owner switches these filters to that owner's master data instead.
        $masterDataOwnerId = $ownerId ?? ($filters['owner_id'] ?? null);
        if ($this->isAdmin() && !$masterDataOwnerId) {
            $masterDataOwnerId = $this->effectiveOwnerId();
        }

        $stages = LeadStage::forOwner($masterDataOwnerId)->orderBy('order_index')->get();

        // The table's own page / sort / Quick search requests (index.blade.php, DataTables ajax):
        // just this page's rows plus the counts.
        if ($request->boolean('dt')) {
            return response()->json([
                'total' => $listingTotal,
                'filtered' => $leads->total(),
                'rows' => view('portal.crm.leads._lead_rows', [
                    'leads' => $leads,
                    'stages' => $stages,
                    'isAdmin' => $this->isAdmin(),
                    'leadTableColumns' => $leadTableColumns,
                ])->render(),
            ]);
        }

        $sources = LeadSource::forOwner($masterDataOwnerId)->orderBy('order_index')->get();
        $tags = LeadTag::forOwner($masterDataOwnerId)->orderBy('name')->get();

        $viewData = [
            'leads' => $leads,
            'listing' => $listing,
            'listingTotal' => $listingTotal,
            // The stat cards ignore the Quick search, like the filters' own counts.
            'quickCounts' => $this->leadService->quickFilterCounts($ownerId, \Illuminate\Support\Arr::except($filters, 'table_search')),
            'stages' => $stages,
            'sources' => $sources,
            'tags' => $tags,
            'owners' => $this->isAdmin() ? PortalUser::orderBy('name')->get() : collect(),
            'filters' => $filters,
            'isAdmin' => $this->isAdmin(),
            'currentOwnerId' => $this->effectiveOwnerId(),
            'leadTableColumns' => $leadTableColumns,
            'leadTableFields' => $this->leadTablePreferenceService->orderedAvailableColumns(),
            // Table Fields › "Reset to default".
            'leadTableDefaultOrder' => array_keys($this->leadTablePreferenceService->availableColumns()),
            'leadTableGroups' => LeadTablePreferenceService::GROUPS,
            // A background file import still running, or the one a "Lead import finished" notification opened.
            'leadImport' => $this->leadImportForBanner($request),
            'isAgencyViewer' => (bool) $this->owner()?->isAgency(),
            'agencyAgents' => $this->owner()?->isAgency() ? $this->owner()->eligibleAgentsQuery()->get(['portal_users.id', 'portal_users.name']) : collect(),
            'unassignedCount' => $this->owner()?->isAgency() ? Lead::where('portal_user_id', $this->owner()->id)->whereNull('agent_id')->count() : 0,
            // Lead assignment modal (portal/agents/_lead_assignment) — agencies only.
            'assignSetting' => $this->owner()?->isAgency()
                ? ($this->owner()->leadAssignmentSetting ?? new \App\Models\AgencyLeadAssignmentSetting(['mode' => \App\Models\AgencyLeadAssignmentSetting::MODE_AUTOMATIC]))
                : null,
        ];

        // The "reload after Create/Edit/Delete" AJAX call re-requests this same
        // action with an XHR header and only needs the listing fragment back —
        // same query/stats/master-data building above, just a smaller view.
        if ($request->ajax()) {
            return view('portal.crm.leads._listing', $viewData);
        }

        return view('portal.crm.leads.index', $viewData);
    }

    /** Persist the current viewer's optional DataTable fields. */
    public function updateTableColumns(Request $request)
    {
        $validated = $request->validate([
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string', 'max:30'],
        ]);

        return response()->json([
            'success' => true,
            'columns' => $this->leadTablePreferenceService->saveUserColumns($validated['columns'] ?? []),
            'message' => 'Table fields saved.',
        ]);
    }

    public function store(StoreLeadRequest $request)
    {
        $ownerId = $this->effectiveOwnerId();
        abort_if(!$ownerId, 403);

        if ($this->isAdmin() && $request->filled('owner_id')) {
            $ownerId = (int) $request->input('owner_id');
        }

        // Same email / phone as an existing lead of this owner → that lead is updated instead.
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

        $message = match (true) {
            $lead->wasRestored => 'This contact was in Deleted Leads as "' . $lead->name . '" — that lead was restored and updated instead of creating a duplicate.',
            $lead->wasMerged => 'This contact already exists as "' . $lead->name . '" — the existing lead was updated instead of creating a duplicate.',
            default => 'Lead created.',
        };

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'id' => $lead->id, 'merged' => $lead->wasMerged]);
        }

        return redirect()->route('portal.crm.leads.index')->with('success', $message);
    }

    protected function findOwned($id, bool $withTrashed = false): Lead
    {
        return $this->leadService->getLead($this->ownerId(), $id, $withTrashed);
    }

    /** Owning account only — an agency agent can work an assigned agency lead but not delete/restore it. */
    protected function findStrictlyOwned($id, bool $withTrashed = false): Lead
    {
        return $this->leadService->getOwnedLead($this->ownerId(), $id, $withTrashed);
    }

    /** The owning agency (or Super Admin) may hand an agency lead to one of that agency's agents. */
    protected function canAssign(Lead $lead): bool
    {
        return ($this->isAdmin() || ($this->ownerId() && $lead->portal_user_id === $this->ownerId()))
            && $lead->owner?->isAgency();
    }

    /**
     * JSON payload for the Lead View/Edit modal — the lead's own values plus
     * the Stage/Source/Tag master data scoped to *the viewer's own* owner
     * (their effectiveOwnerId — Admin's shared owner for Super Admin, their
     * own id for an Agent/Company), not necessarily the lead's actual owner.
     * For a non-admin this is the same thing, since findOwned() already
     * restricts them to their own leads; for Super Admin editing another
     * owner's lead, it deliberately always shows Admin's own Master data.
     */
    /** Insights tab — the next page of the website visitor's timeline / favorites / saved searches / chats (load on scroll). */
    public function insights(Request $request, $id)
    {
        $visitorLead = $this->findOwned($id)->visitorLead;
        abort_unless($visitorLead, 404);

        return response()->json(app(\App\Services\Visitors\VisitorInsights::class)->feed($visitorLead, $request));
    }

    public function show(Request $request, $id)
    {
        $lead = $this->findOwned($id);

        if (!$request->wantsJson()) {
            return view('portal.crm.leads.show', $this->detailPageData($lead));
        }

        $masterDataOwnerId = $this->effectiveOwnerId();

        return response()->json([
            'id' => $lead->id,
            'name' => $lead->name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'phone_country_code' => $lead->phone_country_code,
            'formatted_phone' => $lead->formatted_phone,
            // Every other email / phone this person has enquired with (repeat enquiries are merged in).
            'alt_emails' => $lead->contacts->where('type', \App\Models\LeadContact::TYPE_EMAIL)
                ->reject(fn ($c) => $c->match_key === \App\Models\LeadContact::emailKey($lead->email))->pluck('value')->values(),
            'alt_phones' => $lead->contacts->where('type', \App\Models\LeadContact::TYPE_PHONE)
                ->reject(fn ($c) => $c->match_key === \App\Models\LeadContact::phoneKey($lead->phone))
                ->map(fn ($c) => trim(($c->phone_country_code && !str_starts_with($c->value, '+') ? $c->phone_country_code . ' ' : '') . $c->value))->values(),
            'enquiry_count' => (int) $lead->enquiry_count,
            'last_enquired_at' => $lead->last_enquired_at?->format('d M Y, H:i'),
            'message' => $lead->message,
            'status' => $lead->status,
            'stage_id' => $lead->stage_id,
            'source_id' => $lead->source_id,
            'owner_id' => $lead->portal_user_id,
            'tag_ids' => $lead->tags->pluck('id'),
            'property_title' => $lead->property?->getTranslation('title'),
            'owner_name' => $lead->owner?->displayName(),
            'owner_is_agency' => (bool) $lead->owner?->isAgency(),
            'agent_id' => $lead->agent_id,
            'agent_name' => $lead->agent?->name,
            'assignment_label' => $lead->assignmentLabel(),
            'assigned_at' => $lead->assigned_at?->format('d M Y, H:i'),
            'can_assign' => $canAssign = $this->canAssign($lead),
            'agent_options' => $canAssign ? $lead->owner->eligibleAgentsQuery()->get(['portal_users.id', 'portal_users.name']) : [],
            'assignment_history' => $lead->assignmentHistory()->with('agent:id,name')->get()->map(fn ($row) => [
                'assigned_at' => $row->assigned_at->format('d M Y, H:i'),
                'agent_name' => $row->agent?->name,
                'label' => (new Lead(['assignment_type' => $row->assignment_type]))->assignmentLabel() ?? $row->assignment_type,
                'by' => $row->assigned_by_type === 'system' ? null : ucfirst($row->assigned_by_type),
                'note' => $row->note,
            ]),
            'stage_name' => $lead->stage?->name,
            'stage_color' => $lead->stage?->color,
            'source_name' => $lead->source?->name,
            'tags' => $lead->tags->map(fn ($tag) => ['id' => $tag->id, 'name' => $tag->name, 'color' => $tag->color]),
            'created_at' => $lead->created_at->format('d M Y, H:i'),
            'is_admin' => $this->isAdmin(),
            'master_data_owner_id' => $masterDataOwnerId,
            'stages' => LeadStage::forOwner($masterDataOwnerId)->orderBy('order_index')->get(['id', 'name']),
            'sources' => LeadSource::forOwner($masterDataOwnerId)->orderBy('order_index')->get(['id', 'name']),
            'tags_master' => LeadTag::forOwner($masterDataOwnerId)->orderBy('name')->get(['id', 'name', 'color']),
            'notes_history' => $this->leadNoteService->getHistoryFor($lead)->map(fn ($note) => [
                'id' => $note->id,
                'body' => $note->body,
                'author_name' => $note->author_name,
                'created_at' => $note->created_at->format('d M Y, H:i'),
            ]),
        ]);
    }

    /**
     * The lead detail page: everything about one lead, plus the Stage/Source/Tag options it can
     * be edited with — the viewer's own master data, same as the JSON above, with the lead's
     * current values kept in the lists even when they belong to another owner.
     */
    protected function detailPageData(Lead $lead): array
    {
        $lead->load(['property', 'owner', 'agent', 'stage', 'source', 'tags', 'contacts', 'notesHistory']);
        $details = app(\App\Services\Crm\LeadDetailService::class);
        $masterDataOwnerId = $this->effectiveOwnerId();

        $withCurrent = fn ($list, $current) => $current && !$list->contains('id', $current->id) ? $list->push($current) : $list;
        $stages = $withCurrent(LeadStage::forOwner($masterDataOwnerId)->orderBy('order_index')->get(), $lead->stage);
        $sources = $withCurrent(LeadSource::forOwner($masterDataOwnerId)->orderBy('order_index')->get(), $lead->source);
        $tags = LeadTag::forOwner($masterDataOwnerId)->orderBy('name')->get();
        $tags = $tags->concat($lead->tags->reject(fn ($t) => $tags->contains('id', $t->id)))->values();

        $canAssign = $this->canAssign($lead);
        $ownedByViewer = $this->isAdmin() || $lead->portal_user_id === $this->ownerId();

        // Previous / next lead in the viewer's list order (newest first), for the header arrows.
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
            'alternateContacts' => $details->alternateContacts($lead),
            'enquiryDetails' => $details->enquiryDetails($lead),
            'timeline' => $details->timeline($lead),
            // Listings this lead bought / rented (Mark as sold on a listing card).
            'purchases' => \App\Models\Property::where('sold_lead_id', $lead->id)->orderByDesc('sold_at')
                ->get(['id', 'translations', 'reference_no', 'segment', 'currency', 'price', 'sold_at', 'sold_type', 'sold_price', 'rented_until']),
            'sourceHistory' => $details->sourceHistory($lead),
            // Website activity of the visitor this lead came from (AI chat, views, time spent…) — Insights tab.
            'visitorInsights' => $lead->visitorLead ? app(\App\Services\Visitors\VisitorInsights::class)->for($lead->visitorLead) : null,
            'notes' => $lead->notesHistory->where('type', \App\Models\LeadNote::TYPE_NOTE)->sortByDesc('id')->values(),
            'stages' => $stages,
            'sources' => $sources,
            'tags' => $tags,
            'isAdmin' => $this->isAdmin(),
            'owners' => $this->isAdmin() ? PortalUser::orderBy('name')->get() : collect(),
            'canAssign' => $canAssign,
            'agentOptions' => $canAssign ? $lead->owner->eligibleAgentsQuery()->get(['portal_users.id', 'portal_users.name']) : collect(),
            'assignmentHistory' => $lead->assignmentHistory()->with('agent:id,name')->get(),
            'canDelete' => $ownedByViewer,
        ];
    }

    /** Lead page → Phone Numbers / Email Addresses "+". */
    public function addContact(Request $request, $id)
    {
        $lead = $this->findOwned($id);
        $data = $request->validate([
            'type' => ['required', Rule::in([\App\Models\LeadContact::TYPE_EMAIL, \App\Models\LeadContact::TYPE_PHONE])],
            'value' => $request->input('type') === 'email' ? \App\Rules\PhoneNumber::emailRules() : \App\Rules\PhoneNumber::rules(true),
            'phone_country_code' => \App\Rules\PhoneNumber::countryCodeRules(),
        ]);

        $this->leadService->addContact($lead, $data['type'], $data['value'], $data['phone_country_code'] ?? null);

        return response()->json(['success' => true, 'message' => ($data['type'] === 'email' ? 'Email' : 'Phone number') . ' added.']);
    }

    public function setPrimaryContact($id, $contactId)
    {
        $lead = $this->findOwned($id);
        $this->leadService->setPrimaryContact($lead, $lead->contacts()->findOrFail($contactId));

        return response()->json(['success' => true, 'message' => 'Primary contact updated.']);
    }

    public function removeContact($id, $contactId)
    {
        $lead = $this->findOwned($id);
        $this->leadService->removeContact($lead, $lead->contacts()->findOrFail($contactId));

        return response()->json(['success' => true, 'message' => 'Removed.']);
    }

    /**
     * Lead page inline edits — one card at a time (Name, Lead Basic Details, Source, Status,
     * Owner), so only the fields sent are validated and saved. Each change is logged by the
     * Lead model's activity hook.
     */
    public function updateFields(Request $request, $id)
    {
        $lead = $this->findOwned($id);
        $viewerOwnerId = $this->effectiveOwnerId();
        $leadOrViewer = function ($query) use ($lead, $viewerOwnerId) {
            $query->where(function ($q) use ($lead, $viewerOwnerId) {
                $q->where('portal_user_id', $lead->portal_user_id);
                if ($viewerOwnerId && $viewerOwnerId !== $lead->portal_user_id) {
                    $q->orWhere('portal_user_id', $viewerOwnerId);
                }
                $q->orWhere('portal_user_id', \App\Models\LeadStage::globalOwnerId()); // Super Admin's global items
            });
        };

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

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Lead updated.', 'id' => $lead->id]);
        }

        return redirect()->route('portal.crm.leads.index')->with('success', 'Lead updated.');
    }

    /** Updates only the pipeline stage from the Leads table's inline stage picker. */
    public function updateStage(Request $request, $id)
    {
        $lead = $this->findOwned($id);
        $viewerOwnerId = $this->effectiveOwnerId();

        $request->validate([
            'stage_id' => [
                'nullable',
                'integer',
                Rule::exists('lead_stages', 'id')->where(function ($query) use ($lead, $viewerOwnerId) {
                    $query->where('portal_user_id', $lead->portal_user_id);

                    if ($viewerOwnerId && $viewerOwnerId !== $lead->portal_user_id) {
                        $query->orWhere('portal_user_id', $viewerOwnerId);
                    }
                    $query->orWhere('portal_user_id', \App\Models\LeadStage::globalOwnerId());
                }),
            ],
        ]);

        $lead->update(['stage_id' => $request->input('stage_id') ?: null]);
        $stage = $lead->stage()->first();

        return response()->json([
            'success' => true,
            'message' => 'Stage updated.',
            'stage' => $stage ? ['id' => $stage->id, 'name' => $stage->name, 'color' => $stage->color] : null,
        ]);
    }

    /** Replaces a lead's tags from the listing's lightweight tag manager. */
    public function syncTags(Request $request, $id)
    {
        $lead = $this->findOwned($id);
        $viewerOwnerId = $this->effectiveOwnerId();

        $belongsToLeadOrViewer = function ($query) use ($lead, $viewerOwnerId) {
            $query->where('portal_user_id', $lead->portal_user_id);

            if ($viewerOwnerId && $viewerOwnerId !== $lead->portal_user_id) {
                $query->orWhere('portal_user_id', $viewerOwnerId);
            }
            $query->orWhere('portal_user_id', \App\Models\LeadStage::globalOwnerId());
        };

        $request->validate([
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', Rule::exists('lead_tags', 'id')->where($belongsToLeadOrViewer)],
        ]);

        $this->leadService->syncTags($lead, $request->input('tags') ?: []);

        return response()->json([
            'success' => true,
            'message' => 'Tags updated.',
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $this->leadService->deleteLead($this->findStrictlyOwned($id));

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Lead deleted.']);
        }

        return redirect()->route('portal.crm.leads.index')->with('success', 'Lead deleted.');
    }

    /**
     * Tags / Stages popups on the listing: the viewer's own master data, searched and paged on
     * the server (20 per request, the popup loads more on scroll) — never the whole list.
     */
    public function masterOptions(Request $request, string $type)
    {
        $ownerId = $this->effectiveOwnerId();
        $search = trim((string) $request->input('search'));

        if ($type === 'stages' && ($owner = $this->owner())) {
            LeadStage::seedDefaultsFor($owner); // no-op once the account has stages
        }

        $query = $type === 'stages'
            ? LeadStage::forOwner($ownerId)->withCount('leads')->orderBy('order_index')
            : LeadTag::forOwner($ownerId)->withCount('leads')->orderBy('name');

        $page = $query
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%' . $search . '%'))
            ->select(['id', 'name', 'color'])
            ->paginate(20, ['*'], 'page', max(1, (int) $request->input('page', 1)));

        return response()->json([
            'data' => collect($page->items())->map(fn ($row) => [
                'id' => $row->id, 'name' => $row->name, 'color' => $row->color, 'leads' => (int) $row->leads_count,
            ]),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
            'total' => $page->total(),
        ]);
    }

    /** Selected leads → one stage (a stage is single per lead). Each change lands in that lead's timeline. */
    public function bulkStage(Request $request)
    {
        $data = $request->validate([
            'stage_id' => ['nullable', 'integer', Rule::exists('lead_stages', 'id')->whereIn('portal_user_id', \App\Models\LeadStage::usableOwnerIds($this->effectiveOwnerId()))],
        ], ['stage_id.exists' => 'That stage does not belong to your account.']);
        $ids = $this->selectedLeadsQuery($request)->pluck('leads.id');

        // One save per lead (not a mass update) so each lead's timeline records the change.
        $changed = 0;
        $total = $ids->count();
        \Illuminate\Support\Facades\DB::transaction(function () use ($ids, $data, &$changed) {
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

    /** Selected leads + selected tags → every tag added to every lead (existing tags are kept). */
    public function bulkTags(Request $request)
    {
        $data = $request->validate([
            'tags' => ['required', 'array', 'min:1'],
            'tags.*' => ['integer', Rule::exists('lead_tags', 'id')->whereIn('portal_user_id', \App\Models\LeadStage::usableOwnerIds($this->effectiveOwnerId()))],
        ], ['tags.required' => 'Choose at least one tag.', 'tags.*.exists' => 'One of the tags does not belong to your account.']);
        $ids = $this->selectedLeadsQuery($request)->pluck('leads.id');

        \Illuminate\Support\Facades\DB::transaction(function () use ($ids, $data) {
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
     * The listing's current selection: either ticked ids, or select_all=1 (+ the listing's
     * filters and any unticked exclude_ids) meaning every matching lead on every page.
     */
    protected function selectedLeadsQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $request->validate([
            'select_all' => ['nullable', 'boolean'],
            'ids' => ['required_unless:select_all,1', 'array'],
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

    /** Toolbar "Delete Leads" — the current selection (ticked ids or all matching). */
    public function bulkDestroy(Request $request)
    {
        $ids = $this->selectedLeadsQuery($request)->pluck('leads.id')->all();
        abort_if(!$ids, 422, 'No leads selected.');

        $count = $this->leadService->deleteMany($ids, $this->ownerId());
        $message = $count === 1 ? '1 lead deleted.' : "{$count} leads deleted.";

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'count' => $count]);
        }

        return redirect()->route('portal.crm.leads.index')->with('success', $message);
    }

    public function trashed(Request $request)
    {
        return view('portal.crm.leads.trashed', [
            'leads' => $this->leadService->getTrashedLeads($this->ownerId()),
            'isAdmin' => $this->isAdmin(),
        ]);
    }

    /** Deleted Leads toolbar — restore / permanently delete the ticked leads, or all of them. */
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

        return redirect()->route('portal.crm.leads.trashed')
            ->with('success', $data['action'] === 'restore' ? "{$noun} restored." : "{$noun} permanently deleted.");
    }

    public function restore($id)
    {
        $this->leadService->restoreLead($this->findStrictlyOwned($id, withTrashed: true));

        return redirect()->route('portal.crm.leads.trashed')->with('success', 'Lead restored.');
    }

    public function forceDestroy($id)
    {
        $this->leadService->forceDeleteLead($this->findStrictlyOwned($id, withTrashed: true));

        return redirect()->route('portal.crm.leads.trashed')->with('success', 'Lead permanently deleted.');
    }

    /** Manual assign / reassign / unassign (agent_id null) of an agency lead — owning agency or Super Admin. */
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

        $message = $lead->agent ? "Lead assigned to {$lead->agent->name}." : 'Lead moved back to agency level.';

        return $request->wantsJson()
            ? response()->json(['success' => true, 'message' => $message, 'agent_id' => $lead->agent_id])
            : back()->with('success', $message);
    }

    /** Round-robins every agency-level unassigned lead across the agency's active agents, on request only. */
    public function distributeUnassigned(Request $request, LeadAssignmentService $assignment)
    {
        $agency = $this->owner();
        abort_unless($agency?->isAgency(), 403);

        $count = $assignment->distributeUnassigned($agency, AssignmentActor::portal($agency));
        $message = $count ? "{$count} unassigned " . ($count === 1 ? 'lead' : 'leads') . ' distributed.' : 'No active agents to distribute to.';

        return $request->wantsJson()
            ? response()->json(['success' => true, 'message' => $message, 'count' => $count])
            : redirect()->route('portal.crm.leads.index')->with('success', $message);
    }

    public function export(Request $request)
    {
        // From the toolbar (POST): the current selection — ticked leads or all matching.
        // Plain GET keeps exporting everything that matches the filters.
        $query = $request->isMethod('post')
            ? $this->selectedLeadsQuery($request)
            : $this->leadService->filteredQuery($this->ownerId(), $this->filtersFromRequest($request));

        return Excel::download(
            new LeadsExport($query),
            'leads-' . now()->format('Y-m-d-His') . '.xlsx'
        );
    }

    public function downloadImportTemplate()
    {
        return Excel::download(new LeadsImportTemplateExport(), 'lead-import-template.xlsx');
    }

    public function importForm()
    {
        return view('portal.crm.leads.import');
    }

    public function downloadFacebookImportSample()
    {
        return Excel::download(new FacebookLeadsImportSampleExport(), 'facebook-leads-sample.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    /**
     * A lead file — our template / a Leads export ("template") or a Facebook leads CSV ("facebook").
     * Its format is checked here, so a wrong file is reported straight away in the popup; the rows
     * are imported in the background (ProcessLeadImport) — a file can hold 10,000+ leads. The popup
     * (AJAX) gets the import's progress URL back and shows it live.
     */
    public function import(Request $request)
    {
        $owner = $this->effectiveOwner();
        abort_if(!$owner, 403);

        $data = $request->validate([
            'import_type' => ['required', Rule::in([LeadsImport::FORMAT_TEMPLATE, LeadsImport::FORMAT_FACEBOOK])],
            'file' => ['required', 'file', 'extensions:xlsx,xls,csv,tsv,txt', 'max:10240'],
        ], [
            'file.extensions' => 'Upload an Excel (.xlsx, .xls) or CSV file.',
        ]);
        $file = $request->file('file');

        if ($running = LeadImport::where('portal_user_id', $owner->id)->whereIn('status', [LeadImport::STATUS_QUEUED, LeadImport::STATUS_RUNNING])->latest('id')->first()?->failIfStale()) {
            if ($running->inProgress()) {
                return $this->importFailed($request, "{$running->file_name} is still importing — please wait for it to finish before uploading another file.");
            }
        }

        try {
            [$headers, $rows] = LeadImportReader::read($file);
        } catch (\Throwable $e) {
            report($e);

            return $this->importFailed($request, 'This file could not be read. Please upload a valid Excel (.xlsx, .xls) or CSV file.');
        }

        $check = new LeadsImport($owner->id, $owner->displayName(), $data['import_type'], $this->leadService, app(LeadCreationService::class));
        if ($error = $check->prepare($headers, $rows)) {
            return $this->importFailed($request, $error);
        }

        $dir = "lead-imports/{$owner->id}/" . \Illuminate\Support\Str::uuid();
        $path = $file->storeAs($dir, 'source.' . strtolower($file->getClientOriginalExtension()), 'local');

        $leadImport = LeadImport::create([
            'portal_user_id' => $owner->id,
            'admin_id' => $this->isAdmin() ? auth('cms')->id() : null,
            'format' => $data['import_type'],
            'file_name' => \Illuminate\Support\Str::limit($file->getClientOriginalName(), 250, ''),
            'file_path' => $path,
            'status' => LeadImport::STATUS_QUEUED,
            'total_rows' => count($rows),
        ]);
        ProcessLeadImport::start($leadImport);

        $message = 'Importing ' . number_format(count($rows)) . ' rows in the background — you\'ll get a notification and a summary email when it\'s done.';

        return $request->wantsJson()
            ? response()->json(['message' => $message, 'import' => $leadImport->toProgress()])
            : redirect()->route('portal.crm.leads.index', ['import' => $leadImport->id])->with('success', $message);
    }

    /** Live progress of a background import (polled by the Leads page / import popup). */
    public function importStatus(LeadImport $leadImport)
    {
        $this->authorizeLeadImport($leadImport);

        return response()->json(['import' => $leadImport->failIfStale()->toProgress()]);
    }

    /** The uploaded file with its Import Status / Import Remarks columns. */
    public function downloadImportResult(LeadImport $leadImport)
    {
        $this->authorizeLeadImport($leadImport);
        abort_unless($leadImport->result_path && Storage::disk('local')->exists($leadImport->result_path), 404, 'This status file is no longer available (files are kept for 30 days).');

        return Storage::disk('local')->download($leadImport->result_path, $leadImport->resultName());
    }

    /** The import to show on the Leads page: the one a notification links to, else one still running. */
    private function leadImportForBanner(Request $request): ?LeadImport
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

    private function authorizeLeadImport(LeadImport $leadImport): void
    {
        abort_unless($leadImport->portal_user_id === $this->effectiveOwnerId(), 404);
    }

    /** A file that can't be imported: JSON for the popup, else back to the form (shown once — in the popup, not the page alert). */
    private function importFailed(Request $request, string $message)
    {
        return $request->wantsJson()
            ? response()->json(['message' => $message], 422)
            : back()->withInput()->with('importError', $message);
    }
}
