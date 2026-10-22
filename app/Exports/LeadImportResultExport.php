<?php

namespace App\Exports;

use App\Imports\LeadsImport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The uploaded file handed back after an import — every row as it was uploaded, plus
 * "Import Status" (Added / Updated / Skipped) and "Import Remarks" (lead #, or why it was skipped).
 * Attached to the import summary email and downloadable from the Leads page.
 */
class LeadImportResultExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    private const COLORS = [
        LeadsImport::STATUS_ADDED => 'FFDCFCE7',
        LeadsImport::STATUS_UPDATED => 'FFDBEAFE',
        LeadsImport::STATUS_SKIPPED => 'FFFEE2E2',
    ];

    public function __construct(private readonly LeadsImport $import)
    {
    }

    public function headings(): array
    {
        return [...$this->import->headers, 'Import Status', 'Import Remarks'];
    }

    public function array(): array
    {
        $width = count($this->import->headers);

        return array_map(fn ($row, $index) => [
            ...array_pad(array_slice($row, 0, $width), $width, ''),
            $this->import->results[$index]['status'] ?? '',
            $this->import->results[$index]['remarks'] ?? '',
        ], $this->import->rows, array_keys($this->import->rows));
    }

    public function styles(Worksheet $sheet): array
    {
        $statusColumn = count($this->import->headers) + 1;
        foreach ($this->import->rows as $index => $row) {
            if ($color = self::COLORS[$this->import->results[$index]['status'] ?? ''] ?? null) {
                $sheet->getStyle([$statusColumn, $index + 2, $statusColumn + 1, $index + 2])
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($color);
            }
        }

        return [1 => ['font' => ['bold' => true]]];
    }
}
