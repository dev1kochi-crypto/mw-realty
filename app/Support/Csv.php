<?php

namespace App\Support;

class Csv
{
    public static function row($stream, array $values): void
    {
        fputcsv($stream, array_map(function ($value) {
            if (is_string($value) && preg_match('/^[\s\x00-\x1f]*[=+@-]/u', $value)) return "'".$value;
            return $value;
        }, $values));
    }
}
