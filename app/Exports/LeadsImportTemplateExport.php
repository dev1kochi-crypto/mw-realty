<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * The downloadable "Download Import Template" file. Its headings are the
 * single source of truth LeadsImport::REQUIRED_COLUMNS is written to match —
 * change one, change the other.
 */
class LeadsImportTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return ['Name', 'Email', 'Phone', 'Message', 'Tags', 'Source', 'Stage', 'Notes', 'Owner'];
    }

    public function array(): array
    {
        return [
            ['Jane Doe', 'jane@example.com', '+971500000000', 'Interested in this listing', 'Hot, VIP', 'Website', 'New', 'Called once, no answer', 'Admin'],
        ];
    }
}
