<?php

namespace Tests\Unit;

use App\Support\Csv;
use PHPUnit\Framework\TestCase;

class CsvTest extends TestCase
{
    public function test_formula_cells_are_neutralized_without_changing_numbers(): void
    {
        $stream = fopen('php://memory', 'r+');
        Csv::row($stream, ['=1+1', "\t@SUM(A1)", 'Normal', 12]);
        rewind($stream);
        $this->assertSame(["'=1+1", "'\t@SUM(A1)", 'Normal', '12'], fgetcsv($stream));
        fclose($stream);
    }
}
