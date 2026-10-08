<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A lead file being (or already) imported in the background — see ProcessLeadImport.
 * $agents: agent name => leads round robin gave them.
 */
class LeadImport extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    /** A running import whose progress hasn't moved for this long lost its worker. */
    public const STALE_AFTER_MINUTES = 15;

    protected $fillable = [
        'portal_user_id', 'admin_id', 'format', 'file_name', 'file_path', 'result_path', 'status',
        'total_rows', 'processed_rows', 'added', 'updated', 'skipped', 'agents', 'error', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'agents' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(PortalUser::class, 'portal_user_id');
    }

    public function inProgress(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }

    public function percent(): int
    {
        return $this->total_rows ? (int) min(100, floor($this->processed_rows * 100 / $this->total_rows)) : 0;
    }

    /** "Report.csv" → "Report-import-status.xlsx" */
    public function resultName(): string
    {
        return pathinfo($this->file_name, PATHINFO_FILENAME) . '-import-status.xlsx';
    }

    public function formatLabel(): string
    {
        return $this->format === \App\Imports\LeadsImport::FORMAT_FACEBOOK ? 'Facebook leads import' : 'Lead import';
    }

    /** A running import that stopped moving (worker killed / server restart) is marked failed. */
    public function failIfStale(): self
    {
        if ($this->status === self::STATUS_RUNNING && $this->updated_at?->lt(now()->subMinutes(self::STALE_AFTER_MINUTES))) {
            $this->forceFill([
                'status' => self::STATUS_FAILED,
                'error' => 'The import stopped unexpectedly. Leads saved before it stopped are kept — please upload the file again; already-imported rows will be matched, not duplicated.',
                'finished_at' => now(),
            ])->save();
        }

        return $this;
    }

    /** What the Leads page polls (status endpoint) and renders on first load. */
    public function toProgress(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'file' => $this->file_name,
            'total' => $this->total_rows,
            'processed' => $this->processed_rows,
            'percent' => $this->percent(),
            'added' => $this->added,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'error' => $this->error,
            'result_url' => $this->result_path ? route('portal.crm.leads.import.result', $this) : null,
            'status_url' => route('portal.crm.leads.import.status', $this),
        ];
    }
}
