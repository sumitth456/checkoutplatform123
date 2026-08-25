<?php

namespace App\Modules\Product\Import\Services;

use App\Modules\Product\Import\ProductImportFieldRegistry;

final class ProductImportValidationService
{
    /**
     * @param  list<array<string, mixed>>  $mappedRows
     * @return array{
     *     errors: list<array{row_number: int, sku: ?string, field: ?string, code: string, message: string, row_data: array<string, mixed>}>,
     *     products: array<string, array{product: array<string, mixed>, variants: list<array<string, mixed>>}>
     * }
     */
    public function validate(array $mappedRows): array
    {
        $errors = [];
        $skuNames = [];
        $variantSkus = [];
        $products = [];

        foreach ($mappedRows as $row) {
            $rowNumber = (int) ($row['_row_number'] ?? 0);
            $sku = $this->normalizeSku($row['sku'] ?? null);
            $rowData = $this->rowDataWithoutMeta($row);

            if ($sku === null) {
                $errors[] = $this->error($rowNumber, null, 'sku', 'missing_sku', 'SKU is required.', $rowData);

                continue;
            }

            if (isset($skuNames[$sku]) && $skuNames[$sku] !== $this->stringValue($row['name'] ?? null)) {
                $errors[] = $this->error(
                    $rowNumber,
                    $sku,
                    'sku',
                    'duplicate_sku',
                    'Duplicate product SKU with conflicting product name in file.',
                    $rowData,
                );
            } elseif (! isset($skuNames[$sku])) {
                $skuNames[$sku] = $this->stringValue($row['name'] ?? null);
            }

            if (! $this->isValidPrice($row['price'] ?? null)) {
                $errors[] = $this->error(
                    $rowNumber,
                    $sku,
                    'price',
                    'invalid_price',
                    'Price must be a non-negative number.',
                    $rowData,
                );
            }

            if (! $this->isValidInventory($row['inventory'] ?? null)) {
                $errors[] = $this->error(
                    $rowNumber,
                    $sku,
                    'inventory',
                    'invalid_inventory',
                    'Inventory must be a non-negative whole number.',
                    $rowData,
                );
            }

            if (! $this->isValidOptionalInventory($row['variant_inventory'] ?? null)) {
                $errors[] = $this->error(
                    $rowNumber,
                    $sku,
                    'variant_inventory',
                    'invalid_inventory',
                    'Variant inventory must be a non-negative whole number.',
                    $rowData,
                );
            }

            if (! $this->isValidOptionalPrice($row['variant_price'] ?? null)) {
                $errors[] = $this->error(
                    $rowNumber,
                    $sku,
                    'variant_price',
                    'invalid_price',
                    'Variant price must be a non-negative number.',
                    $rowData,
                );
            }

            $imageUrl = $this->stringValue($row['image_url'] ?? null);

            if ($imageUrl !== null && ! $this->isValidImageUrl($imageUrl)) {
                $errors[] = $this->error(
                    $rowNumber,
                    $sku,
                    'image_url',
                    'invalid_image_url',
                    'Image URL must be a valid http or https URL.',
                    $rowData,
                );
            }

            $variantSku = $this->normalizeSku($row['variant_sku'] ?? null);

            if ($variantSku !== null) {
                if (isset($variantSkus[$variantSku])) {
                    $errors[] = $this->error(
                        $rowNumber,
                        $sku,
                        'variant_sku',
                        'duplicate_variant_sku',
                        'Duplicate variant SKU in file.',
                        $rowData,
                    );
                } else {
                    $variantSkus[$variantSku] = true;
                }
            }

            if ($this->stringValue($row['name'] ?? null) === null) {
                $errors[] = $this->error(
                    $rowNumber,
                    $sku,
                    'name',
                    'missing_name',
                    'Product name is required.',
                    $rowData,
                );
            }

            if (! isset($products[$sku])) {
                $products[$sku] = [
                    'product' => $row,
                    'variants' => [],
                ];
            }

            if ($this->rowHasVariantData($row)) {
                $products[$sku]['variants'][] = [
                    'variant' => $this->stringValue($row['variant'] ?? null),
                    'variant_sku' => $variantSku,
                    'variant_price' => $row['variant_price'] ?? null,
                    'variant_inventory' => $row['variant_inventory'] ?? null,
                    '_row_number' => $rowNumber,
                ];
            }
        }

        $validProducts = [];

        foreach ($products as $sku => $group) {
            $hasErrorsForSku = collect($errors)->contains(
                fn (array $error): bool => $this->normalizeSku($error['sku'] ?? null) === $sku,
            );

            if ($hasErrorsForSku) {
                continue;
            }

            if (! $this->passesRequiredMappedFields($group['product'])) {
                continue;
            }

            $validProducts[$sku] = $group;
        }

        return [
            'errors' => $errors,
            'products' => $validProducts,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function passesRequiredMappedFields(array $row): bool
    {
        foreach (ProductImportFieldRegistry::requiredMappedFields() as $field) {
            $value = $row[$field] ?? null;

            if ($field === 'price') {
                if (! $this->isValidPrice($value)) {
                    return false;
                }

                continue;
            }

            if ($this->stringValue($value) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowHasVariantData(array $row): bool
    {
        return filled($row['variant'] ?? null)
            || filled($row['variant_sku'] ?? null)
            || filled($row['variant_price'] ?? null)
            || filled($row['variant_inventory'] ?? null);
    }

    private function normalizeSku(mixed $value): ?string
    {
        $sku = $this->stringValue($value);

        if ($sku === null) {
            return null;
        }

        return strtoupper($sku);
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function isValidPrice(mixed $value): bool
    {
        if ($value === null || trim((string) $value) === '') {
            return false;
        }

        $numeric = $this->toNumber($value);

        return $numeric !== null && $numeric >= 0;
    }

    private function isValidOptionalPrice(mixed $value): bool
    {
        if ($value === null || trim((string) $value) === '') {
            return true;
        }

        $numeric = $this->toNumber($value);

        return $numeric !== null && $numeric >= 0;
    }

    private function isValidInventory(mixed $value): bool
    {
        if ($value === null || trim((string) $value) === '') {
            return true;
        }

        if (! is_numeric($value)) {
            return false;
        }

        $int = (int) $value;

        return (string) $int === (string) (int) round((float) $value) && $int >= 0;
    }

    private function isValidOptionalInventory(mixed $value): bool
    {
        return $this->isValidInventory($value);
    }

    private function isValidImageUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return str_starts_with(strtolower($url), 'http://')
            || str_starts_with(strtolower($url), 'https://');
    }

    private function toNumber(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = preg_replace('/[^0-9.\-]/', '', (string) $value) ?? '';

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function rowDataWithoutMeta(array $row): array
    {
        unset($row['_row_number']);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $rowData
     * @return array{row_number: int, sku: ?string, field: ?string, code: string, message: string, row_data: array<string, mixed>}
     */
    private function error(
        int $rowNumber,
        ?string $sku,
        ?string $field,
        string $code,
        string $message,
        array $rowData,
    ): array {
        return [
            'row_number' => $rowNumber,
            'sku' => $sku,
            'field' => $field,
            'code' => $code,
            'message' => $message,
            'row_data' => $rowData,
        ];
    }
}
