<?php

namespace App\Http\Controllers\Crm\Properties;

use App\Exports\PropertiesImportTemplateExport;
use App\Http\Controllers\Crm\Concerns\ScopesListings;
use App\Imports\LeadImportReader;
use App\Imports\PropertiesImport;
use App\Jobs\ProcessPropertyImport;
use App\Models\Property;
use App\Models\PropertyImport;
use App\Models\PortalUser;
use App\Services\AutoTranslator;
use App\Services\Properties\ListingCopywriter;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @group CRM Properties — Import
 *
 * Bulk-adding listings from a spreadsheet — next to the Property Finder sync (Integrations), for
 * agencies whose inventory lives in Excel or another CRM's export. Two steps: upload the file (it
 * is read at once and the columns are matched to our fields), then confirm the mapping to start
 * the import in the background (ProcessPropertyImport) and poll its progress.
 */
class PropertyImportController extends Controller
{
    use ScopesListings;

    /**
     * Import history
     *
     * The account's past imports, newest first, 10 a page (the Import page's history table).
     *
     * @queryParam page integer Example: 1
     * @response 200 {"data": [{"id": 4, "status": "done", "file": "inventory.xlsx", "total": 120, "added": 110, "created_at": "2026-10-30T10:00:00Z", "uploaded_by": "Sara"}], "next_page": null}
     */
    public function index(Request $request)
    {
        $page = PropertyImport::where('portal_user_id', $this->listingOwner()->id)
            ->where('status', '!=', PropertyImport::STATUS_MAPPING)
            ->with('uploader:id,name')
            ->latest('id')
            ->simplePaginate(10, ['*'], 'page', max(1, $request->integer('page', 1)));

        return response()->json([
            'data' => collect($page->items())->map(fn (PropertyImport $import) => $import->failIfStale()->toProgress() + [
                'created_at' => $import->created_at?->toIso8601String(),
                'uploaded_by' => $import->uploader?->name,
            ]),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ]);
    }

    /**
     * Upload a listing file
     *
     * Returns the file's columns (with sample values), our fields and the suggested mapping.
     *
     * @bodyParam file file required .xlsx, .xls or .csv, max 10 MB.
     * @bodyParam segment string residential | commercial (the page it's uploaded from). Example: residential
     *
     * @response 200 {"import": {"id": 4, "status": "mapping", "file": "inventory.xlsx", "total": 120}, "columns": [{"index": 0, "heading": "Ref", "samples": ["AG-1"]}], "fields": [{"key": "price", "label": "Price (AED)", "required": true, "hint": "…"}], "mapping": {"reference": 0}, "ai_available": true, "translate_available": true}
     */
    public function store(Request $request)
    {
        $owner = $this->listingOwner();
        $data = $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx,xls,csv,tsv,txt', 'max:10240'],
            'segment' => ['nullable', Rule::in([Property::SEGMENT_RESIDENTIAL, Property::SEGMENT_COMMERCIAL])],
        ], ['file.extensions' => 'Upload an Excel (.xlsx, .xls) or CSV file.']);
        $file = $request->file('file');

        $running = PropertyImport::where('portal_user_id', $owner->id)
            ->whereIn('status', [PropertyImport::STATUS_QUEUED, PropertyImport::STATUS_RUNNING])->latest('id')->first()?->failIfStale();
        if ($running?->inProgress()) {
            return $this->failed("{$running->file_name} is still importing — please wait for it to finish before uploading another file.");
        }
        if ($owner->remainingPropertySlots() === 0) {
            return $this->failed('Your plan\'s listing limit is reached — upgrade your plan to import more listings.');
        }

        try {
            [$headers, $rows] = LeadImportReader::read($file);
        } catch (\Throwable $e) {
            report($e);

            return $this->failed('This file could not be read. Please upload a valid Excel (.xlsx, .xls) or CSV file.');
        }
        $rows = array_values(array_filter($rows, fn ($row) => !LeadImportReader::isBlank($row)));
        if (!$headers || !$rows) {
            return $this->failed('The file needs a heading row and at least one listing below it.');
        }
        if (count($rows) > PropertiesImport::MAX_ROWS) {
            return $this->failed('This file has ' . number_format(count($rows)) . ' listings — import at most ' . number_format(PropertiesImport::MAX_ROWS) . ' per file (split it into several files).');
        }

        $path = $file->storeAs("property-imports/{$owner->id}/" . Str::uuid(), 'source.' . strtolower($file->getClientOriginalExtension()), 'local');
        $import = PropertyImport::create([
            'portal_user_id' => $owner->id,
            'uploaded_by_id' => $this->viewer()->id,
            'segment' => $data['segment'] ?? Property::SEGMENT_RESIDENTIAL,
            'file_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'file_path' => $path,
            'status' => PropertyImport::STATUS_MAPPING,
            'total_rows' => count($rows),
        ]);
        ProcessPropertyImport::pruneOldFiles($owner->id);

        return response()->json([
            'import' => $import->toProgress(),
            'columns' => collect($headers)->map(fn ($heading, $index) => [
                'index' => $index,
                'heading' => $heading !== '' ? $heading : 'Column ' . ($index + 1),
                'samples' => collect($rows)->pluck($index)->map(fn ($v) => Str::limit((string) $v, 60))->filter(fn ($v) => $v !== '')->unique()->take(3)->values(),
            ])->values(),
            'fields' => PropertiesImport::fieldList(),
            'mapping' => (object) PropertiesImport::suggestMapping($headers),
            'ai_available' => app(ListingCopywriter::class)->available(),
            'translate_available' => AutoTranslator::configured(),
        ]);
    }

    /**
     * Start the import
     *
     * @bodyParam mapping object required Our field => the file's column index. Example: {"price": 3, "community": 5}
     * @bodyParam translate boolean Translate title / description / location into the other site languages.
     * @bodyParam ai_descriptions boolean Write a description with AI for rows that have none.
     *
     * @response 200 {"message": "Importing 120 listings in the background…", "import": {"id": 4, "status": "queued"}}
     */
    public function start(Request $request, PropertyImport $propertyImport)
    {
        $this->authorizeImport($propertyImport);
        abort_unless($propertyImport->status === PropertyImport::STATUS_MAPPING, 422, 'This file has already been imported — upload it again to import it once more.');
        $data = $request->validate([
            'mapping' => 'required|array',
            'mapping.*' => 'nullable|integer|min:0|max:500',
            'translate' => 'boolean',
            'ai_descriptions' => 'boolean',
        ]);
        $mapping = array_map(fn ($v) => $v === null ? null : (int) $v, array_intersect_key($data['mapping'], PropertiesImport::FIELDS));

        // Same check the job runs, so a wrong mapping is reported here and not in a failed import.
        $disk = Storage::disk('local');
        [$headers, $rows] = LeadImportReader::read(new \Illuminate\Http\UploadedFile($disk->path($propertyImport->file_path), $propertyImport->file_name, null, null, true));
        $check = new PropertiesImport($propertyImport->owner, $this->viewer(), $propertyImport->segment, $mapping);
        if ($error = $check->prepare($headers, $rows)) {
            return $this->failed($error, 'mapping');
        }

        $propertyImport->update([
            'mapping' => $mapping,
            'options' => ['translate' => $request->boolean('translate'), 'ai_descriptions' => $request->boolean('ai_descriptions')],
            'status' => PropertyImport::STATUS_QUEUED,
            'total_rows' => count($rows),
        ]);
        ProcessPropertyImport::start($propertyImport);

        return response()->json([
            'message' => 'Importing ' . number_format(count($rows)) . ' rows in the background — you\'ll get a notification when it\'s done.',
            'import' => $propertyImport->toProgress(),
        ]);
    }

    /**
     * Latest import
     *
     * The account's latest import still running or finished in the last day (the Properties page
     * shows it on load), or a given one with `?id=`.
     */
    public function latest(Request $request)
    {
        $import = PropertyImport::where('portal_user_id', $this->listingOwner()->id)
            ->where('status', '!=', PropertyImport::STATUS_MAPPING)
            ->when($request->integer('id'), fn ($q, $id) => $q->whereKey($id), fn ($q) => $q->where('updated_at', '>=', now()->subDay()))
            ->latest('id')->first()?->failIfStale();

        return response()->json(['import' => $import?->toProgress()]);
    }

    /** Import progress */
    public function status(PropertyImport $propertyImport)
    {
        $this->authorizeImport($propertyImport);

        return response()->json(['import' => $propertyImport->failIfStale()->toProgress()]);
    }

    /**
     * Import status file
     *
     * The uploaded file with Import Status / Import Remarks columns added (kept 30 days).
     */
    public function result(PropertyImport $propertyImport)
    {
        $this->authorizeImport($propertyImport);
        abort_unless($propertyImport->result_path && Storage::disk('local')->exists($propertyImport->result_path), 404, 'This status file is no longer available (files are kept for 30 days).');

        return Storage::disk('local')->download($propertyImport->result_path, $propertyImport->resultName());
    }

    /** Import template */
    public function template()
    {
        return Excel::download(new PropertiesImportTemplateExport(), 'listing-import-template.xlsx');
    }

    /** The account listings are imported into: an agency agent imports for the agency. Portal accounts only. */
    private function listingOwner(): PortalUser
    {
        $viewer = $this->viewer();
        abort_unless($viewer, 403, 'Sign in as the agency or agent to import their listings.');
        abort_unless($viewer->status === 'approved', 403, 'Your account needs to be approved before you can add properties.');

        return $viewer->listingOwner();
    }

    private function authorizeImport(PropertyImport $propertyImport): void
    {
        abort_unless($propertyImport->portal_user_id === $this->listingOwner()->id, 404);
    }

    private function failed(string $message, string $field = 'file')
    {
        return response()->json(['message' => $message, 'errors' => [$field => [$message]]], 422);
    }
}
