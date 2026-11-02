<?php

namespace App\Exports;

use App\Imports\PropertiesImport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The uploaded listing spreadsheet handed back after an import — every row as uploaded, plus
 * "Import Status" (Added / Needs details / Duplicate / Skipped) and "Import Remarks" (the new
 * reference no and what's still needed, or why it was skipped).
 */
class PropertyImportResultExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    private const COLORS = [
        PropertiesImport::STATUS_ADDED => 'FFDCFCE7',
        PropertiesImport::STATUS_NEEDS_INFO => 'FFFEF3C7',
        PropertiesImport::STATUS_DUPLICATE => 'FFDBEAFE',
        PropertiesImport::STATUS_SKIPPED => 'FFFEE2E2',
    ];

    public function __construct(private readonly PropertiesImport $import)
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
