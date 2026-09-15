<?php

namespace App\Http\Controllers\Portal\Crm;

use App\Exports\LeadsExport;
use App\Exports\LeadsImportTemplateExport;
use App\Http\Controllers\Portal\Crm\Concerns\ScopesPortalOwner;
use App\Http\Requests\Crm\StoreLeadRequest;
use App\Http\Requests\Crm\UpdateLeadRequest;
use App\Imports\LeadsImport;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStage;
use App\Models\LeadTag;
use App\Models\PortalUser;
use App\Services\Crm\LeadNoteService;
use App\Services\Crm\LeadService;
use App\Services\Crm\LeadTablePreferenceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
            'stage_id' => $request->input('stage_id'),
            'source_id' => $request->input('source_id'),
            'tag_id' => $request->input('tag_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
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
        $leads = $this->leadService->getAllFilteredLeads($ownerId, $filters);

        // In the Super Admin's global view, use the same shared Admin master
        // data managed under Master > Stage, Tag, and Source. Selecting an
        // owner switches these filters to that owner's master data instead.
        $masterDataOwnerId = $ownerId ?? ($filters['owner_id'] ?? null);
        if ($this->isAdmin() && !$masterDataOwnerId) {
            $masterDataOwnerId = $this->effectiveOwnerId();
        }

        $stages = LeadStage::forOwner($masterDataOwnerId)->orderBy('order_index')->get();
        $sources = LeadSource::forOwner($masterDataOwnerId)->orderBy('order_index')->get();
        $tags = LeadTag::forOwner($masterDataOwnerId)->orderBy('name')->get();

        $viewData = [
            'leads' => $leads,
            'stages' => $stages,
            'sources' => $sources,
            'tags' => $tags,
            'owners' => $this->isAdmin() ? PortalUser::orderBy('name')->get() : collect(),
            'filters' => $filters,
            'isAdmin' => $this->isAdmin(),
            'currentOwnerId' => $this->effectiveOwnerId(),
            'leadTableColumns' => $this->leadTablePreferenceService->getUserColumns(),
            'leadTableFields' => $this->leadTablePreferenceService->availableColumns(),
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

        $lead = $this->leadService->createLead([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'phone_country_code' => $request->filled('phone') ? $request->input('phone_country_code') : null,
            'message' => $request->input('message'),
            'status' => $request->input('status', 'active'),
            'stage_id' => $request->input('stage_id') ?: null,
            'source_id' => $request->input('source_id') ?: null,
        ], $ownerId, $request->input('tags', []));

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Lead created.', 'id' => $lead->id]);
        }

        return redirect()->route('portal.crm.leads.index')->with('success', 'Lead created.');
    }

    protected function findOwned($id, bool $withTrashed = false): Lead
    {
        return $this->leadService->getLead($this->ownerId(), $id, $withTrashed);
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
    public function show(Request $request, $id)
    {
        $lead = $this->findOwned($id);

        if (!$request->wantsJson()) {
            return redirect()->route('portal.crm.leads.index', ['lead' => $lead->id]);
        }

        $masterDataOwnerId = $this->effectiveOwnerId();

        return response()->json([
            'id' => $lead->id,
            'name' => $lead->name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'phone_country_code' => $lead->phone_country_code,
            'formatted_phone' => $lead->formatted_phone,
            'message' => $lead->message,
            'status' => $lead->status,
            'stage_id' => $lead->stage_id,
            'source_id' => $lead->source_id,
            'owner_id' => $lead->portal_user_id,
            'tag_ids' => $lead->tags->pluck('id'),
            'property_title' => $lead->property?->getTranslation('title'),
            'owner_name' => $lead->owner?->displayName(),
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
            'stage_id' => $request->input('stage_id') ?: null,
            'source_id' => $request->input('source_id') ?: null,
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
        };

        $request->validate([
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', Rule::exists('lead_tags', 'id')->where($belongsToLeadOrViewer)],
        ]);

        $lead->tags()->sync($request->input('tags', []));

        return response()->json([
            'success' => true,
            'message' => 'Tags updated.',
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $this->leadService->deleteLead($this->findOwned($id));

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Lead deleted.']);
        }

        return redirect()->route('portal.crm.leads.index')->with('success', 'Lead deleted.');
    }

    /** Backs the listing's checkbox-select "Delete Selected" action. */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $count = $this->leadService->deleteMany($request->input('ids'), $this->ownerId());
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

    public function restore($id)
    {
        $this->leadService->restoreLead($this->findOwned($id, withTrashed: true));

        return redirect()->route('portal.crm.leads.trashed')->with('success', 'Lead restored.');
    }

    public function forceDestroy($id)
    {
        $this->leadService->forceDeleteLead($this->findOwned($id, withTrashed: true));

        return redirect()->route('portal.crm.leads.trashed')->with('success', 'Lead permanently deleted.');
    }

    public function export(Request $request)
    {
        $filters = $this->filtersFromRequest($request);

        return Excel::download(
            new LeadsExport($this->ownerId(), $filters, $this->leadService),
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

    public function import(Request $request)
    {
        $ownerId = $this->effectiveOwnerId();
        abort_if(!$ownerId, 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $ownerDisplayName = $this->effectiveOwner()->displayName();
        $import = new LeadsImport($ownerId, $ownerDisplayName, $this->leadService);

        Excel::import($import, $request->file('file'));

        if ($import->structureError) {
            return back()->with('error', $import->structureError);
        }

        return redirect()->route('portal.crm.leads.index')->with('importResult', [
            'imported' => $import->imported,
            'skipped' => count($import->rowErrors),
            'rowErrors' => $import->rowErrors,
        ]);
    }
}
