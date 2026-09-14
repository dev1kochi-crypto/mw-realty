<?php

namespace App\Imports;

use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStage;
use App\Services\Crm\LeadService;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Bulk-creates Leads for one owner from the file produced by
 * LeadsImportTemplateExport (see that class — the two must stay in sync).
 *
 * Structural mismatches (missing/renamed columns) reject the whole file up
 * front with no rows processed. Per-row data problems are skipped and
 * reported individually so a few bad rows don't block the rest of a batch.
 * All valid rows are created inside one DB transaction, so a mid-batch
 * exception rolls everything back rather than leaving a half-imported file.
 */
class LeadsImport implements ToCollection, WithHeadingRow
{
    public const REQUIRED_COLUMNS = ['name', 'email', 'phone', 'message', 'tags', 'source', 'stage', 'notes', 'owner'];

    public ?string $structureError = null;

    public int $imported = 0;

    /** @var array<int, array{row:int, errors:string[]}> */
    public array $rowErrors = [];

    public function __construct(
        private readonly int $ownerId,
        private readonly string $ownerDisplayName,
        private readonly LeadService $leadService,
    ) {
    }

    public function collection(SupportCollection $rows)
    {
        if ($rows->isEmpty()) {
            $this->structureError = 'The uploaded file has no data rows. Please use the provided Excel import template.';

            return;
        }

        $columns = array_keys($rows->first()->toArray());
        $missing = array_diff(self::REQUIRED_COLUMNS, $columns);

        if (!empty($missing)) {
            $this->structureError = 'Please use the provided Excel import template. Leads can only be imported using the supported template format. '
                . 'Missing column(s): ' . implode(', ', $missing) . '.';

            return;
        }

        $stagesByName = LeadStage::where('portal_user_id', $this->ownerId)->get()->keyBy(fn ($s) => strtolower($s->name));
        $sourcesByName = LeadSource::where('portal_user_id', $this->ownerId)->get()->keyBy(fn ($s) => strtolower($s->name));

        DB::transaction(function () use ($rows, $stagesByName, $sourcesByName) {
            foreach ($rows as $index => $row) {
                $this->importRow($row, $index + 2, $stagesByName, $sourcesByName);
            }
        });
    }

    private function importRow(SupportCollection $row, int $rowNumber, SupportCollection $stagesByName, SupportCollection $sourcesByName): void
    {
        $data = [
            'name' => trim((string) ($row['name'] ?? '')),
            'email' => trim((string) ($row['email'] ?? '')),
            'phone' => trim((string) ($row['phone'] ?? '')),
            'message' => trim((string) ($row['message'] ?? '')),
            'notes' => trim((string) ($row['notes'] ?? '')),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        /** @var string[] $customErrors Collected separately — $validator->errors() returns a fresh
         *  MessageBag on each call, so mutating it directly here would never affect fails(). */
        $customErrors = [];

        if ($data['email'] === '' && $data['phone'] === '') {
            $customErrors[] = 'Either email or phone is required.';
        }

        $stageName = trim((string) ($row['stage'] ?? ''));
        $sourceName = trim((string) ($row['source'] ?? ''));
        $ownerName = trim((string) ($row['owner'] ?? ''));

        $stage = $stageName !== '' ? $stagesByName->get(strtolower($stageName)) : null;
        $source = $sourceName !== '' ? $sourcesByName->get(strtolower($sourceName)) : null;

        if ($stageName !== '' && !$stage) {
            $customErrors[] = "Unknown stage \"{$stageName}\" — it must already exist under Master > Stage.";
        }
        if ($sourceName !== '' && !$source) {
            $customErrors[] = "Unknown source \"{$sourceName}\" — it must already exist under Master > Source.";
        }
        // Leads are always imported into the current account — the Owner column is a sanity check, not a cross-account reassignment.
        if ($ownerName !== '' && strcasecmp($ownerName, $this->ownerDisplayName) !== 0) {
            $customErrors[] = "Owner \"{$ownerName}\" does not match your account (\"{$this->ownerDisplayName}\").";
        }

        if ($data['email'] !== '' && Lead::where('portal_user_id', $this->ownerId)->where('email', $data['email'])->where('name', $data['name'])->exists()) {
            $customErrors[] = 'A lead with this name and email already exists.';
        }

        if ($validator->fails() || !empty($customErrors)) {
            $this->rowErrors[] = ['row' => $rowNumber, 'errors' => array_merge($validator->errors()->all(), $customErrors)];

            return;
        }

        $tagNames = array_filter(array_map('trim', explode(',', (string) ($row['tags'] ?? ''))));
        $tagIds = $this->leadService->resolveTagIds($this->ownerId, $tagNames);

        $this->leadService->createLead([
            'name' => $data['name'],
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
            'message' => $data['message'] ?: null,
            'notes' => $data['notes'] ?: null,
            'stage_id' => $stage?->id,
            'source_id' => $source?->id,
            'status' => 'active',
        ], $this->ownerId, $tagIds);

        $this->imported++;
    }
}
