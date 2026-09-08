<?php

namespace App\Support;

final class CsvFormulaSanitizer
{
    public static function sanitize(string $value): string
    {
        return preg_match('/^\s*[=+\-@]/u', $value) === 1
            ? "'{$value}"
            : $value;
    }
}
