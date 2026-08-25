<?php

namespace App\Modules\Product\Import\Enums;

enum ProductImportFormat: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';

    public static function fromFilename(string $filename): ?self
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => self::Csv,
            'xlsx' => self::Xlsx,
            default => null,
        };
    }
}
