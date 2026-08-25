<?php

namespace App\Modules\Campaign\Services;

use App\Models\CampaignProduct;
use App\Models\Variant;
use App\Modules\Variant\Enums\VariantStatus;

final class CampaignVariantMatrixService
{
    /**
     * @return array{
     *     colors: list<string>,
     *     sizes: list<string>,
     *     variants: list<array<string, mixed>>,
     *     matrix: array<string, array<string, string>>
     * }
     */
    public function build(CampaignProduct $line): array
    {
        $product = $line->product;

        if ($product === null) {
            return ['colors' => [], 'sizes' => [], 'variants' => [], 'matrix' => []];
        }

        $product->loadMissing(['variants' => fn ($q) => $q->where('status', VariantStatus::Active)]);

        $variants = [];
        $colors = [];
        $sizes = [];
        $matrix = [];

        foreach ($product->variants as $variant) {
            $color = $this->optionValue($variant, ['color', 'Color']);
            $size = $this->optionValue($variant, ['size', 'Size']) ?? $variant->name;

            if ($color !== null) {
                $colors[] = $color;
            }

            if ($size !== null) {
                $sizes[] = $size;
            }

            $inStock = $this->isInStock($variant);

            $variants[] = [
                'uuid' => $variant->uuid,
                'sku' => $variant->sku,
                'name' => $variant->name,
                'color' => $color,
                'size' => $size,
                'options' => $variant->options ?? [],
                'price' => $this->formatMoney($line->campaign_price ?? $variant->price),
                'compare_at_price' => $this->formatMoney($variant->compare_at_price ?? $product->compare_at_price),
                'currency' => strtoupper((string) ($variant->currency ?: $product->currency ?: 'USD')),
                'in_stock' => $inStock,
                'inventory_quantity' => $variant->track_inventory ? (int) $variant->inventory_quantity : null,
                'images' => $this->resolveImages($variant, $product),
            ];

            if ($color !== null && $size !== null) {
                $matrix[$color][$size] = $variant->uuid;
            }
        }

        return [
            'colors' => $this->uniqueSorted($colors),
            'sizes' => $this->uniqueSorted($sizes),
            'variants' => $variants,
            'matrix' => $matrix,
        ];
    }

    public function findVariantUuid(CampaignProduct $line, ?string $color, ?string $size): ?string
    {
        $matrix = $this->build($line);

        if ($color !== null && $size !== null && isset($matrix['matrix'][$color][$size])) {
            return $matrix['matrix'][$color][$size];
        }

        foreach ($matrix['variants'] as $variant) {
            if ($color !== null && ($variant['color'] ?? null) !== $color) {
                continue;
            }

            if ($size !== null && ($variant['size'] ?? null) !== $size) {
                continue;
            }

            return $variant['uuid'];
        }

        return null;
    }

    /**
     * @param  list<string>  $keys
     */
    private function optionValue(Variant $variant, array $keys): ?string
    {
        $options = (array) ($variant->options ?? []);

        foreach ($keys as $key) {
            if (isset($options[$key]) && filled($options[$key])) {
                return (string) $options[$key];
            }
        }

        return null;
    }

    private function isInStock(Variant $variant): bool
    {
        if (! $variant->track_inventory) {
            return true;
        }

        return (int) $variant->inventory_quantity > 0;
    }

    /**
     * @return list<string>
     */
    private function resolveImages(Variant $variant, $product): array
    {
        $images = array_values(array_filter((array) ($variant->images ?? [])));

        if ($images !== []) {
            return $images;
        }

        return array_values(array_filter((array) ($product->images ?? [])));
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function uniqueSorted(array $values): array
    {
        return collect($values)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function formatMoney(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
