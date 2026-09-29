<?php

namespace App\Exports;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exports the leads of a query built by LeadService — filteredQuery() (everything the listing
 * shows) or selectedQuery() (the toolbar selection) — so Export always matches the screen.
 */
class LeadsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private readonly Builder $query)
    {
    }

    public function query()
    {
        return $this->query->with(['owner', 'agent', 'stage', 'source', 'tags']);
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
