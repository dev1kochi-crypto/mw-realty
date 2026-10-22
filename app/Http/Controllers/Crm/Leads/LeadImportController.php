<?php

namespace App\Http\Controllers\Crm\Leads;

use App\Exports\FacebookLeadsImportSampleExport;
use App\Exports\LeadsImportTemplateExport;
use App\Http\Controllers\Crm\Concerns\ScopesPortalOwner;
use App\Imports\LeadImportReader;
use App\Imports\LeadsImport;
use App\Jobs\ProcessLeadImport;
use App\Models\LeadImport;
use App\Services\Crm\LeadCreationService;
use App\Services\Crm\LeadService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @group CRM Leads
 *
 * Importing leads from a file: our template / a Leads export, or a Facebook leads CSV. The file
 * is checked straight away; its rows are imported in the background (ProcessLeadImport — a file
 * can hold 10,000+ leads), so poll the import's `status_url` for progress.
 */
class LeadImportController extends Controller
{
    use ScopesPortalOwner;

    public function __construct(private readonly LeadService $leadService)
    {
    }

    /**
     * Import leads
     *
     * @bodyParam import_type string required template or facebook. Example: template
     * @bodyParam file file required .xlsx, .xls or .csv, max 10 MB.
     *
     * @response 200 {"message": "Importing 250 rows in the background — you'll get a notification and a summary email when it's done.", "import": {"id": 9, "status": "queued", "file": "leads.xlsx", "total": 250, "processed": 0, "percent": 0}}
     * @response 422 {"message": "This file could not be read. Please upload a valid Excel (.xlsx, .xls) or CSV file."}
     */
    public function store(Request $request)
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

        $running = LeadImport::where('portal_user_id', $owner->id)
            ->whereIn('status', [LeadImport::STATUS_QUEUED, LeadImport::STATUS_RUNNING])->latest('id')->first()?->failIfStale();
        if ($running?->inProgress()) {
            return $this->failed("{$running->file_name} is still importing — please wait for it to finish before uploading another file.");
        }

        try {
            [$headers, $rows] = LeadImportReader::read($file);
        } catch (\Throwable $e) {
            report($e);

            return $this->failed('This file could not be read. Please upload a valid Excel (.xlsx, .xls) or CSV file.');
        }

        $check = new LeadsImport($owner->id, $owner->displayName(), $data['import_type'], $this->leadService, app(LeadCreationService::class));
        if ($error = $check->prepare($headers, $rows)) {
            return $this->failed($error);
        }

        $path = $file->storeAs("lead-imports/{$owner->id}/" . Str::uuid(), 'source.' . strtolower($file->getClientOriginalExtension()), 'local');

        $leadImport = LeadImport::create([
            'portal_user_id' => $owner->id,
            'admin_id' => $this->isAdmin() ? auth('cms')->id() : null,
            'format' => $data['import_type'],
            'file_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'file_path' => $path,
            'status' => LeadImport::STATUS_QUEUED,
            'total_rows' => count($rows),
        ]);
        ProcessLeadImport::start($leadImport);

        return response()->json([
            'message' => 'Importing ' . number_format(count($rows)) . ' rows in the background — you\'ll get a notification and a summary email when it\'s done.',
            'import' => $leadImport->toProgress(),
        ]);
    }

    /**
     * Import progress
     *
     * @response 200 {"import": {"id": 9, "status": "running", "file": "leads.xlsx", "total": 250, "processed": 120, "percent": 48, "added": 100, "updated": 15, "skipped": 5, "error": null, "result_url": null, "status_url": "…"}}
     */
    public function status(LeadImport $leadImport)
    {
        $this->authorizeImport($leadImport);

        return response()->json(['import' => $leadImport->failIfStale()->toProgress()]);
    }

    /**
     * Import status file
     *
     * The uploaded file with Import Status / Import Remarks columns added (kept 30 days).
     */
    public function result(LeadImport $leadImport)
    {
        $this->authorizeImport($leadImport);
        abort_unless($leadImport->result_path && Storage::disk('local')->exists($leadImport->result_path), 404, 'This status file is no longer available (files are kept for 30 days).');

        return Storage::disk('local')->download($leadImport->result_path, $leadImport->resultName());
    }

    /**
     * Import template
     *
     * The Excel template to fill in.
     */
    public function template()
    {
        return Excel::download(new LeadsImportTemplateExport(), 'lead-import-template.xlsx');
    }

    /**
     * Facebook CSV sample
     *
     * What a Facebook leads export looks like.
     */
    public function facebookSample()
    {
        return Excel::download(new FacebookLeadsImportSampleExport(), 'facebook-leads-sample.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    private function authorizeImport(LeadImport $leadImport): void
    {
        abort_unless($leadImport->portal_user_id === $this->effectiveOwnerId(), 404);
    }

    private function failed(string $message)
    {
        return response()->json(['message' => $message, 'errors' => ['file' => [$message]]], 422);
    }
}
