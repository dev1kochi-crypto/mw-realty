<?php

namespace App\Exports;

use App\Models\Lead;
use App\Services\Crm\LeadService;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exports Leads using the exact same owner+filter query the Lead listing
 * shows on screen (via LeadService::filteredQuery) — Export always matches
 * whatever the user currently has filtered/searched for.
 */
class LeadsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        private readonly ?int $ownerId,
        private readonly array $filters,
        private readonly LeadService $leadService,
    ) {
    }

    public function query()
    {
        return $this->leadService->filteredQuery($this->ownerId, $this->filters);
    }

    public function headings(): array
    {
        return ['Name', 'Email', 'Phone', 'Message', 'Tags', 'Source', 'Stage', 'Notes', 'Owner', 'Status', 'Received At', 'Updated At'];
    }

    public function map($lead): array
    {
        /** @var Lead $lead */
        return [
            $lead->name,
            $lead->email,
            $lead->formatted_phone,
            $lead->message,
            $lead->tags->pluck('name')->implode(', '),
            $lead->source?->name,
            $lead->stage?->name,
            $lead->notes,
            $lead->owner?->displayName(),
            ucfirst($lead->status),
            optional($lead->created_at)->format('Y-m-d H:i'),
            optional($lead->updated_at)->format('Y-m-d H:i'),
        ];
    }
}
