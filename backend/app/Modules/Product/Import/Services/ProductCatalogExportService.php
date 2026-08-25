<?php

namespace App\Modules\Product\Import\Services;

use App\Models\Product;
use App\Modules\Product\Import\ProductImportFieldRegistry;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\Common\Creator\WriterFactory;

final class ProductCatalogExportService
{
    /**
     * @param  array{funnel_id?: int|null, status?: string|null, search?: string|null, format?: string|null}  $filters
     */
    public function export(array $filters = []): string
    {
        $format = strtolower((string) ($filters['format'] ?? 'csv'));
        $extension = $format === 'xlsx' ? 'xlsx' : 'csv';
        $filename = sprintf('product-catalog-%s.%s', now()->format('Ymd-His'), $extension);
        $relativePath = 'exports/'.$filename;
        $absolutePath = storage_path('app/'.$relativePath);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0775, true);
        }

        $writer = WriterFactory::createFromFile($absolutePath);
        $writer->openToFile($absolutePath);

        $headers = array_values(ProductImportFieldRegistry::fields());
        $writer->addRow(Row::fromValues($headers));

        $this->query($filters)
            ->with(['variants', 'category', 'brand', 'subscriptionPlan'])
            ->orderBy('id')
            ->chunkById(200, function ($products) use ($writer): void {
                foreach ($products as $product) {
                    foreach ($this->productRows($product) as $row) {
                        $writer->addRow(Row::fromValues($row));
                    }
                }
            });

        $writer->close();

        return $absolutePath;
    }

    /**
     * @param  array{funnel_id?: int|null, status?: string|null, search?: string|null}  $filters
     */
    private function query(array $filters): Builder
    {
        $query = Product::query();

        if (! empty($filters['funnel_id'])) {
            $query->whereHas('funnels', function (Builder $builder) use ($filters): void {
                $builder->where('funnels.id', (int) $filters['funnel_id']);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        return $query;
    }

    /**
     * @return list<list<string|null>>
     */
    private function productRows(Product $product): array
    {
        $base = [
            $product->sku,
            $product->name,
            $product->description,
            $product->category?->name,
            $product->brand?->name,
            $this->formatMoney($product->base_price),
            $this->formatMoney($product->compare_at_price),
            $this->formatMoney($product->cost),
            $product->weight !== null ? (string) $product->weight : null,
            (string) $product->totalInventoryQuantity(),
            $product->is_subscription ? 'yes' : 'no',
            $product->subscriptionPlan?->name,
            $product->status?->value,
            $product->type?->value,
            $this->primaryImageUrl($product),
        ];

        $variants = $product->variants;

        if ($variants->isEmpty()) {
            return [array_merge($base, [null, null, null, null])];
        }

        $rows = [];

        foreach ($variants as $variant) {
            $rows[] = array_merge($base, [
                $variant->name,
                $variant->sku,
                $this->formatMoney($variant->price),
                (string) $variant->inventory_quantity,
            ]);
        }

        return $rows;
    }

    private function formatMoney(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function primaryImageUrl(Product $product): ?string
    {
        $images = $product->images ?? [];

        if ($images === []) {
            return null;
        }

        $first = $images[0];

        return is_string($first) ? $first : null;
    }
}
