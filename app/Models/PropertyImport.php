<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A listing spreadsheet being (or already) imported — see PropertiesImport / ProcessPropertyImport.
 * $mapping: import field => file column index. $options: translate, ai_descriptions.
 */
class PropertyImport extends Model
{
    public const STATUS_MAPPING = 'mapping';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    /** A running import whose progress hasn't moved for this long lost its worker. */
    public const STALE_AFTER_MINUTES = 15;

    protected $fillable = [
        'portal_user_id', 'uploaded_by_id', 'segment', 'file_name', 'file_path', 'result_path', 'status', 'mapping', 'options',
        'total_rows', 'processed_rows', 'added', 'complete', 'duplicates', 'skipped', 'ai_written', 'translated', 'error', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'mapping' => 'array',
        'options' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(PortalUser::class, 'portal_user_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(PortalUser::class, 'uploaded_by_id');
    }

    public function inProgress(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }

    public function percent(): int
    {
        return $this->total_rows ? (int) min(100, floor($this->processed_rows * 100 / $this->total_rows)) : 0;
    }

    /** Share of the file's rows that became listings needing nothing more than permit validation. */
    public function automatedPercent(): int
    {
        return $this->total_rows ? (int) round($this->complete * 100 / $this->total_rows) : 0;
    }

    /** "Inventory.csv" → "Inventory-import-status.xlsx" */
    public function resultName(): string
    {
        return pathinfo($this->file_name, PATHINFO_FILENAME) . '-import-status.xlsx';
    }

    public function failIfStale(): self
    {
        if ($this->status === self::STATUS_RUNNING && $this->updated_at?->lt(now()->subMinutes(self::STALE_AFTER_MINUTES))) {
            $this->forceFill([
                'status' => self::STATUS_FAILED,
                'error' => 'The import stopped unexpectedly. Listings saved before it stopped are kept — upload the file again; rows already imported are recognised as duplicates, not added twice.',
                'finished_at' => now(),
            ])->save();
        }

        return $this;
    }

    /** What the Properties page polls and renders. */
    public function toProgress(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'segment' => $this->segment,
            'file' => $this->file_name,
            'total' => $this->total_rows,
            'processed' => $this->processed_rows,
            'percent' => $this->percent(),
            'added' => $this->added,
            'complete' => $this->complete,
            'needs_info' => max(0, $this->added - $this->complete),
            'duplicates' => $this->duplicates,
            'skipped' => $this->skipped,
            'ai_written' => $this->ai_written,
            'translated' => $this->translated,
            'automated_percent' => $this->automatedPercent(),
            'duration_seconds' => $this->started_at && $this->finished_at ? $this->started_at->diffInSeconds($this->finished_at) : null,
            'error' => $this->error,
            'has_result' => (bool) $this->result_path,
        ];
    }
}
