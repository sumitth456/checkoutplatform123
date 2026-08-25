<?php

namespace App\Modules\Product\Import\Services;

use App\Modules\Product\Import\ProductImportFieldRegistry;

final class ProductImportColumnMapper
{
    /**
     * @param  array<int, string>  $headers
     * @return array<string, int|null>
     */
    public function autoDetectMapping(array $headers): array
    {
        $mapping = [];

        foreach (array_keys(ProductImportFieldRegistry::fields()) as $field) {
            $mapping[$field] = null;
        }

        $normalizedHeaders = [];

        foreach ($headers as $index => $header) {
            $normalizedHeaders[$index] = $this->normalizeHeader($header);
        }

        foreach (ProductImportFieldRegistry::headerAliases() as $field => $aliases) {
            foreach ($normalizedHeaders as $index => $normalizedHeader) {
                if ($this->headerMatchesField($normalizedHeader, $field, $aliases)) {
                    $mapping[$field] = $index;

                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * @param  list<array{row_number: int, cells: array<int, string|null>}>  $rows
     * @param  array<string, int|null>  $mapping
     * @return list<array<string, mixed>>
     */
    public function applyMapping(array $rows, array $mapping): array
    {
        $mappedRows = [];

        foreach ($rows as $row) {
            $record = [
                '_row_number' => $row['row_number'],
            ];

            foreach ($mapping as $field => $columnIndex) {
                if ($columnIndex === null) {
                    continue;
                }

                $record[$field] = $row['cells'][$columnIndex] ?? null;
            }

            $mappedRows[] = $record;
        }

        return $mappedRows;
    }

    /**
     * @param  list<string>  $aliases
     */
    private function headerMatchesField(string $normalizedHeader, string $field, array $aliases): bool
    {
        if ($normalizedHeader === $this->normalizeHeader($field)) {
            return true;
        }

        foreach ($aliases as $alias) {
            if ($normalizedHeader === $this->normalizeHeader($alias)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeHeader(string $header): string
    {
        $normalized = strtolower(trim($header));
        $normalized = str_replace(['-', '.'], '_', $normalized);
        $normalized = preg_replace('/\s+/', '_', $normalized) ?? $normalized;

        return $normalized;
    }
}
