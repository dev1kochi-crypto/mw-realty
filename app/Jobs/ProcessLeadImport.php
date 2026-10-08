<?php

namespace App\Jobs;

use App\Exports\LeadImportResultExport;
use App\Imports\LeadImportReader;
use App\Imports\LeadsImport;
use App\Mail\LeadsImportAssignedMail;
use App\Mail\LeadsImportedMail;
use App\Models\CmsKit\Admin;
use App\Models\LeadImport;
use App\Models\PortalUser;
use App\Notifications\LeadImportFinishedNotification;
use App\Services\Crm\LeadCreationService;
use App\Services\Crm\LeadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Imports an uploaded lead file in the background (a file can hold 10,000+ leads) on the "imports"
 * queue — its own worker and a retry_after longer than $timeout, so a long import is never picked
 * up twice. The upload request already checked the file's format (LeadsImport::prepare).
 *
 * Progress goes on the LeadImport row (polled by the Leads page). When done: the status file
 * (upload + Import Status column), one summary email to the account (+ the Super Admin who
 * uploaded it) with that file attached, one to each round-robin agent, and a bell notification.
 */
class ProcessLeadImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;
    public int $timeout = 3600;

    /** How often (rows) progress is written — often enough for a live bar, not a query per row. */
    private const PROGRESS_EVERY = 25;

    public function __construct(public int $leadImportId)
    {
    }

    public static function start(LeadImport $import): void
    {
        static::dispatch($import->id)->onConnection('imports')->onQueue('imports');
    }

    public function handle(LeadService $leadService, LeadCreationService $leadCreation): void
    {
        $record = LeadImport::with('owner')->find($this->leadImportId);
        if (!$record || $record->status !== LeadImport::STATUS_QUEUED || !$record->owner) {
            return;
        }
        $record->forceFill(['status' => LeadImport::STATUS_RUNNING, 'started_at' => now()])->save();
        $disk = Storage::disk('local');

        [$headers, $rows] = LeadImportReader::read(new UploadedFile($disk->path($record->file_path), $record->file_name, null, null, true));
        $import = new LeadsImport($record->owner->id, $record->owner->displayName(), $record->format, $leadService, $leadCreation);
        if ($error = $import->prepare($headers, $rows)) {
            $this->finish($record, ['status' => LeadImport::STATUS_FAILED, 'error' => $error]);

            return;
        }

        $import->process(function (int $processed, int $added, int $updated, int $skipped) use ($record) {
            if ($processed % self::PROGRESS_EVERY === 0) {
                $record->forceFill(['processed_rows' => $processed, 'added' => $added, 'updated' => $updated, 'skipped' => $skipped])->save();
            }
        });

        $resultPath = dirname($record->file_path) . '/result.xlsx';
        Excel::store(new LeadImportResultExport($import), $resultPath, 'local');

        $agents = PortalUser::whereIn('id', array_keys($import->agents))->get()->keyBy('id');
        $this->finish($record, [
            'status' => LeadImport::STATUS_DONE,
            'result_path' => $resultPath,
            'total_rows' => count($import->rows),
            'processed_rows' => count($import->rows),
            'added' => $import->added,
            'updated' => $import->updated,
            'skipped' => $import->skipped,
            'agents' => collect($import->agents)->mapWithKeys(fn ($assigned, $agentId) => [
                ($agents->get($agentId)?->displayName() ?? "Agent #{$agentId}") => $assigned['count'],
            ])->all(),
        ]);

        foreach ($import->agents as $agentId => $assigned) {
            if (($agent = $agents->get($agentId)) && $agent->email) {
                $this->queueMail($agent->email, new LeadsImportAssignedMail($agent, $assigned, $record->owner->displayName()));
            }
        }
    }

    /** The job itself crashed (or timed out) — say so instead of leaving a spinner forever. */
    public function failed(?\Throwable $e): void
    {
        Log::error("Lead import {$this->leadImportId} failed: " . $e?->getMessage());
        if (($record = LeadImport::find($this->leadImportId)) && $record->inProgress()) {
            $this->finish($record, [
                'status' => LeadImport::STATUS_FAILED,
                'error' => 'The import stopped unexpectedly. Leads saved before it stopped are kept — please upload the file again; rows already imported will be matched to their leads, not duplicated.',
            ]);
        }
    }

    /** Save the outcome, then tell the account (+ uploading Super Admin): bell notification + summary email. */
    private function finish(LeadImport $record, array $outcome): void
    {
        $record->forceFill($outcome + ['finished_at' => now()])->save();
        self::pruneOldFiles($record->portal_user_id);

        $owner = $record->owner;
        $admin = $record->admin_id ? Admin::find($record->admin_id) : null;

        foreach (array_filter([$owner, $admin]) as $notifiable) {
            try {
                $notifiable->notify(new LeadImportFinishedNotification($record));
            } catch (\Throwable $e) {
                Log::error('Lead import bell notification failed: ' . $e->getMessage());
            }
        }

        if ($record->status !== LeadImport::STATUS_DONE) {
            return;
        }
        $summary = [
            'file' => $record->file_name,
            'format' => $record->formatLabel(),
            'added' => $record->added,
            'updated' => $record->updated,
            'skipped' => $record->skipped,
            'agents' => $record->agents ?? [],
        ];
        $recipients = array_filter([$owner?->email => $owner?->displayName()]);
        if ($admin?->email) {
            $recipients[$admin->email] ??= $admin->name;
        }
        foreach ($recipients as $email => $name) {
            $this->queueMail($email, new LeadsImportedMail($name, $summary, $record->result_path, $record->resultName()));
        }
    }

    private function queueMail(string $to, Mailable $mail): void
    {
        try {
            Mail::to($to)->queue($mail);
        } catch (\Throwable $e) {
            Log::error('Lead import summary email failed: ' . $e->getMessage());
        }
    }

    /** Uploads and status files are kept 30 days — long enough for the emailed attachment and the download link. */
    public static function pruneOldFiles(int $ownerId): void
    {
        $disk = Storage::disk('local');
        foreach ($disk->directories("lead-imports/{$ownerId}") as $dir) {
            $files = $disk->files($dir);
            if (!$files || max(array_map(fn ($f) => $disk->lastModified($f), $files)) < now()->subDays(30)->getTimestamp()) {
                $disk->deleteDirectory($dir);
            }
        }
    }
}
