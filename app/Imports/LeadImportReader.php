<?php

namespace App\Imports;

use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reads an uploaded lead file into plain [headers, rows] — the first row is the headings, every
 * cell a trimmed string. CSV / TSV / TXT are parsed here rather than by PhpSpreadsheet because
 * Facebook's "Download leads" CSV is UTF-16 with tab separators; the encoding (BOM, UTF-8 or
 * Windows-1252) and the separator (tab, comma or semicolon) are detected. XLSX / XLS go through
 * Laravel Excel, first sheet only. Trailing blank rows (Excel's left-over formatting) are dropped;
 * blank rows between data rows are kept so the import can report them.
 */
class LeadImportReader
{
    /** @return array{0: string[], 1: array<int, string[]>} */
    public static function read(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        $rows = in_array($extension, ['csv', 'tsv', 'txt'], true)
            ? self::readDelimited((string) file_get_contents($file->getRealPath()))
            : (Excel::toArray(new class {}, $file)[0] ?? []);

        $rows = array_map(fn ($row) => array_map([self::class, 'cell'], array_values((array) $row)), $rows);

        while ($rows && self::isBlank(end($rows))) {
            array_pop($rows);
        }

        $headers = array_shift($rows) ?? [];
        // Empty heading cells at the end of the row (formatting in the template) aren't columns.
        while ($headers && end($headers) === '') {
            array_pop($headers);
        }

        return [$headers, array_values($rows)];
    }

    /** @param string[] $row */
    public static function isBlank(array $row): bool
    {
        return implode('', $row) === '';
    }

    /** A heading as a lookup key: "Received At" → "received_at", "how_many…?" → "how_many…". */
    public static function key(string $heading): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', mb_strtolower(trim($heading))), '_');
    }

    private static function readDelimited(string $content): array
    {
        $content = match (true) {
            str_starts_with($content, "\xFF\xFE") => mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16LE'),
            str_starts_with($content, "\xFE\xFF") => mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16BE'),
            str_starts_with($content, "\xEF\xBB\xBF") => substr($content, 3),
            mb_check_encoding($content, 'UTF-8') => $content,
            default => mb_convert_encoding($content, 'UTF-8', 'Windows-1252'),
        };

        $firstLine = strtok($content, "\r\n") ?: '';
        $counts = ["\t" => substr_count($firstLine, "\t"), ',' => substr_count($firstLine, ','), ';' => substr_count($firstLine, ';')];
        arsort($counts);
        $delimiter = (string) array_key_first($counts);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $rows = [];
        while (($row = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $rows[] = $row === [null] ? [] : $row;
        }
        fclose($stream);

        return $rows;
    }

    private static function cell(mixed $value): string
    {
        // Whole numbers typed into Excel (e.g. a phone 971501234567) come back as floats.
        if (is_float($value) && floor($value) === $value && abs($value) < 1e15) {
            return sprintf('%.0f', $value);
        }

        return trim((string) $value);
    }
}
