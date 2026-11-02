<?php

namespace App\Jobs;

use App\Exports\PropertyImportResultExport;
use App\Imports\LeadImportReader;
use App\Imports\PropertiesImport;
use App\Models\PropertyImport;
use App\Notifications\PropertyImportFinishedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Imports a listing spreadsheet in the background on the "imports" queue (like lead imports and
 * the Property Finder sync — photo downloads and AI copy make a row take seconds). Progress goes
 * on the PropertyImport row (polled by the Properties page); when done, the status file (upload +
 * Import Status / Remarks columns) and a bell notification to the account and the uploader.
 */
class ProcessPropertyImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;
    public int $timeout = 3600;

    /** How often (rows) progress is written. Rows are slow (photos), so often. */
    private const PROGRESS_EVERY = 5;

    public function __construct(public int $propertyImportId)
    {
    }

    public static function start(PropertyImport $import): void
    {
        static::dispatch($import->id)->onConnection('imports')->onQueue('imports');
    }

    public function handle(): void
    {
        $record = PropertyImport::with(['owner', 'uploader'])->find($this->propertyImportId);
        if (!$record || $record->status !== PropertyImport::STATUS_QUEUED || !$record->owner) {
            return;
        }
        $record->forceFill(['status' => PropertyImport::STATUS_RUNNING, 'started_at' => now()])->save();
        $disk = Storage::disk('local');

        [$headers, $rows] = LeadImportReader::read(new UploadedFile($disk->path($record->file_path), $record->file_name, null, null, true));
        $import = new PropertiesImport($record->owner, $record->uploader, $record->segment, $record->mapping ?? [], $record->options ?? [], $record->id);
        if ($error = $import->prepare($headers, $rows)) {
            $this->finish($record, ['status' => PropertyImport::STATUS_FAILED, 'error' => $error]);

            return;
        }

        $import->process(function (int $processed) use ($record, $import) {
            if ($processed % self::PROGRESS_EVERY === 0) {
                $record->forceFill($this->totals($import) + ['processed_rows' => $processed])->save();
            }
        });

        $resultPath = dirname($record->file_path) . '/result.xlsx';
        Excel::store(new PropertyImportResultExport($import), $resultPath, 'local');

        $this->finish($record, $this->totals($import) + [
            'status' => PropertyImport::STATUS_DONE,
            'result_path' => $resultPath,
            'total_rows' => count($import->rows),
            'processed_rows' => count($import->rows),
            'error' => $import->stoppedBecause,
        ]);
    }

    public function failed(?\Throwable $e): void
    {
        Log::error("Property import {$this->propertyImportId} failed: " . $e?->getMessage());
        if (($record = PropertyImport::find($this->propertyImportId)) && $record->inProgress()) {
            $this->finish($record, [
                'status' => PropertyImport::STATUS_FAILED,
                'error' => 'The import stopped unexpectedly. Listings saved before it stopped are kept — upload the file again; rows already imported are recognised as duplicates, not added twice.',
            ]);
        }
    }

    private function totals(PropertiesImport $import): array
    {
        return [
            'added' => $import->added,
            'complete' => $import->complete,
            'duplicates' => $import->duplicates,
            'skipped' => $import->skipped,
            'ai_written' => $import->aiWritten,
            'translated' => $import->translated,
        ];
    }

    private function finish(PropertyImport $record, array $outcome): void
    {
        $record->forceFill($outcome + ['finished_at' => now()])->save();
        self::pruneOldFiles($record->portal_user_id);

        $notify = collect([$record->owner, $record->uploader])->filter()->unique('id');
        foreach ($notify as $notifiable) {
            try {
                $notifiable->notify(new PropertyImportFinishedNotification($record));
            } catch (\Throwable $e) {
                Log::error('Property import bell notification failed: ' . $e->getMessage());
            }
        }
    }

    /** Uploads and status files are kept 30 days; uploads never mapped are dropped after a day. */
    public static function pruneOldFiles(int $ownerId): void
    {
        $disk = Storage::disk('local');
        foreach ($disk->directories("property-imports/{$ownerId}") as $dir) {
            $files = $disk->files($dir);
            if (!$files || max(array_map(fn ($f) => $disk->lastModified($f), $files)) < now()->subDays(30)->getTimestamp()) {
                $disk->deleteDirectory($dir);
            }
        }
        PropertyImport::where('portal_user_id', $ownerId)->where('status', PropertyImport::STATUS_MAPPING)
            ->where('created_at', '<', now()->subDay())->get()
            ->each(function (PropertyImport $abandoned) use ($disk) {
                $disk->deleteDirectory(dirname($abandoned->file_path));
                $abandoned->delete();
            });
    }
}
