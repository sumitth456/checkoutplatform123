<?php

namespace App\Modules\Product\BillingCycles;

use App\Models\Campaign;
use App\Models\CampaignProduct;
use App\Models\CampaignProductBillingCycle;
use App\Models\Product;
use App\Models\ProductBillingCycle;
use App\Models\Variant;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use OpenSpout\Writer\Common\Creator\WriterFactory;

final class BillingCycleExportImportService
{
    public function __construct(
        private readonly ProductBillingCycleService $productCycles,
        private readonly \App\Modules\Campaign\Services\CampaignProductBillingCycleService $campaignCycles,
    ) {}

    /**
     * @return array{path: string, filename: string, format: string}
     */
    public function exportProduct(Product $product, string $format = 'json'): array
    {
        $format = strtolower($format);
        $product->load(['billingCycles.billProduct', 'billingCycles.variant']);

        if ($format === 'csv') {
            return $this->writeCsv(
                filenamePrefix: sprintf('product-%s-billing-cycles', $product->sku ?: $product->uuid),
                headers: BillingCycleFieldRegistry::csvHeaders(),
                rows: $product->billingCycles
                    ->sortBy('cycle_number')
                    ->map(fn (ProductBillingCycle $cycle): array => $this->productCycleToCsvRow($cycle))
                    ->values()
                    ->all(),
            );
        }

        return $this->writeJson(
            filenamePrefix: sprintf('product-%s-billing-cycles', $product->sku ?: $product->uuid),
            payload: [
                'product_uuid' => $product->uuid,
                'product_sku' => $product->sku,
                'cycles' => $product->billingCycles
                    ->sortBy('cycle_number')
                    ->map(fn (ProductBillingCycle $cycle): array => $this->productCycleToPayload($cycle))
                    ->values()
                    ->all(),
            ],
        );
    }

    /**
     * @return array{path: string, filename: string, format: string}
     */
    public function exportCampaignProduct(CampaignProduct $campaignProduct, string $format = 'json'): array
    {
        $format = strtolower($format);
        $campaignProduct->load(['product', 'billingCycles.product', 'billingCycles.variant']);

        if ($format === 'csv') {
            return $this->writeCsv(
                filenamePrefix: sprintf('campaign-product-%d-billing-cycles', $campaignProduct->id),
                headers: BillingCycleFieldRegistry::campaignCsvHeaders(),
                rows: $campaignProduct->billingCycles
                    ->sortBy('cycle_number')
                    ->map(fn (CampaignProductBillingCycle $cycle): array => $this->campaignCycleToCsvRow($campaignProduct, $cycle))
                    ->values()
                    ->all(),
            );
        }

        return $this->writeJson(
            filenamePrefix: sprintf('campaign-product-%d-billing-cycles', $campaignProduct->id),
            payload: [
                'campaign_product_id' => $campaignProduct->id,
                'base_product_uuid' => $campaignProduct->product?->uuid,
                'base_product_sku' => $campaignProduct->product?->sku,
                'cycles' => $campaignProduct->billingCycles
                    ->sortBy('cycle_number')
                    ->map(fn (CampaignProductBillingCycle $cycle): array => $this->campaignCycleToPayload($cycle))
                    ->values()
                    ->all(),
            ],
        );
    }

    /**
     * @return array{path: string, filename: string, format: string}
     */
    public function exportCampaign(Campaign $campaign, string $format = 'json'): array
    {
        $format = strtolower($format);
        $lines = $campaign->campaignProducts()
            ->with(['product', 'billingCycles.product', 'billingCycles.variant'])
            ->orderBy('sort_order')
            ->get();

        if ($format === 'csv') {
            $rows = [];

            foreach ($lines as $line) {
                foreach ($line->billingCycles->sortBy('cycle_number') as $cycle) {
                    $rows[] = $this->campaignCycleToCsvRow($line, $cycle);
                }
            }

            return $this->writeCsv(
                filenamePrefix: sprintf('campaign-%s-billing-cycles', $campaign->public_id),
                headers: BillingCycleFieldRegistry::campaignCsvHeaders(),
                rows: $rows,
            );
        }

        return $this->writeJson(
            filenamePrefix: sprintf('campaign-%s-billing-cycles', $campaign->public_id),
            payload: [
                'campaign_uuid' => $campaign->uuid,
                'campaign_public_id' => $campaign->public_id,
                'product_lines' => $lines->map(fn (CampaignProduct $line): array => [
                    'campaign_product_id' => $line->id,
                    'base_product_uuid' => $line->product?->uuid,
                    'base_product_sku' => $line->product?->sku,
                    'cycles' => $line->billingCycles
                        ->sortBy('cycle_number')
                        ->map(fn (CampaignProductBillingCycle $cycle): array => $this->campaignCycleToPayload($cycle))
                        ->values()
                        ->all(),
                ])->values()->all(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>|null  $jsonPayload
     * @return array{imported: int, mode: string}
     */
    public function importProduct(Product $product, ?array $jsonPayload = null, ?string $uploadedPath = null): array
    {
        $rows = $jsonPayload !== null
            ? $this->normalizeJsonImportRows($jsonPayload)
            : $this->readCsvRows($uploadedPath, BillingCycleFieldRegistry::csvHeaders());

        $normalized = [];

        foreach ($rows as $row) {
            $normalized[] = $this->normalizeProductImportRow($product, $row);
        }

        $this->productCycles->syncProductCycles($product, $normalized);

        return ['imported' => count($normalized), 'mode' => 'replace'];
    }

    /**
     * @param  array<string, mixed>|null  $jsonPayload
     * @return array{imported: int, mode: string}
     */
    public function importCampaignProduct(
        CampaignProduct $campaignProduct,
        ?array $jsonPayload = null,
        ?string $uploadedPath = null,
    ): array {
        $rows = $jsonPayload !== null
            ? $this->normalizeJsonImportRows($jsonPayload)
            : $this->readCsvRows($uploadedPath, BillingCycleFieldRegistry::campaignCsvHeaders());

        $normalized = [];

        foreach ($rows as $row) {
            $normalized[] = $this->normalizeCampaignImportRow($campaignProduct, $row);
        }

        $imported = $this->campaignCycles->syncCycles($campaignProduct, $normalized);

        return ['imported' => $imported, 'mode' => 'replace'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function normalizeJsonImportRows(array $payload): array
    {
        if (isset($payload['cycles']) && is_array($payload['cycles'])) {
            return $payload['cycles'];
        }

        if (isset($payload['product_lines']) && is_array($payload['product_lines'])) {
            throw new InvalidArgumentException('Use campaign import endpoint for multi-line campaign payloads.');
        }

        if (array_is_list($payload)) {
            return $payload;
        }

        throw new InvalidArgumentException('Import payload must include a cycles array.');
    }

    /**
     * @param  list<string>  $expectedHeaders
     * @return list<array<string, mixed>>
     */
    private function readCsvRows(?string $path, array $expectedHeaders): array
    {
        if ($path === null || ! is_file($path)) {
            throw new InvalidArgumentException('A valid CSV upload is required.');
        }

        $reader = ReaderFactory::createFromFile($path);
        $reader->open($path);

        $rows = [];
        $header = null;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $index => $row) {
                $values = $row->toArray();

                if ($index === 1) {
                    $header = array_map(static fn ($value): string => strtolower(trim((string) $value)), $values);

                    continue;
                }

                if ($header === null) {
                    continue;
                }

                $assoc = [];
                foreach ($header as $i => $key) {
                    $assoc[$key] = $values[$i] ?? null;
                }

                if (blank($assoc['cycle_number'] ?? null)) {
                    continue;
                }

                $rows[] = $assoc;
            }

            break;
        }

        $reader->close();

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeProductImportRow(Product $owner, array $row): array
    {
        if (isset($row['bill_product_id']) || isset($row['product_id'])) {
            $billProduct = Product::query()->findOrFail((int) ($row['bill_product_id'] ?? $row['product_id']));
        } else {
            $billProduct = $this->resolveProductBySku((string) ($row['bill_product_sku'] ?? $owner->sku), $owner);
        }

        return $this->normalizeCycleAttributes($row, $billProduct);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeCampaignImportRow(CampaignProduct $line, array $row): array
    {
        if (isset($row['bill_product_id']) || isset($row['product_id'])) {
            $billProduct = Product::query()->findOrFail((int) ($row['bill_product_id'] ?? $row['product_id']));
        } else {
            $billProduct = $this->resolveProductBySku((string) ($row['bill_product_sku'] ?? ''), $line->product);
        }

        return $this->normalizeCycleAttributes($row, $billProduct);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeCycleAttributes(array $row, Product $billProduct): array
    {
        $variantSku = filled($row['variant_sku'] ?? null) ? (string) $row['variant_sku'] : null;
        $variantId = null;

        if ($variantSku !== null) {
            $variantId = Variant::query()
                ->where('product_id', $billProduct->id)
                ->where('sku', $variantSku)
                ->value('id');

            if ($variantId === null) {
                throw new InvalidArgumentException("Variant SKU [{$variantSku}] not found for product [{$billProduct->sku}].");
            }
        }

        $couponEnabled = filter_var($row['coupon_enabled'] ?? false, FILTER_VALIDATE_BOOL);

        return [
            'cycle_number' => (int) $row['cycle_number'],
            'product_id' => $billProduct->id,
            'bill_product_id' => $billProduct->id,
            'variant_id' => $variantId,
            'price' => $row['price'] ?? null,
            'shipping_price' => $row['shipping_price'] ?? null,
            'bill_delay_days' => (int) ($row['bill_delay_days'] ?? 0),
            'delay_fulfillment' => filter_var($row['delay_fulfillment'] ?? false, FILTER_VALIDATE_BOOL),
            'shippable' => filter_var($row['shippable'] ?? true, FILTER_VALIDATE_BOOL),
            'warehouse' => filled($row['warehouse'] ?? null) ? (string) $row['warehouse'] : null,
            'subscription_enabled' => filter_var($row['subscription_enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'repeat_forever' => filter_var($row['repeat_forever'] ?? false, FILTER_VALIDATE_BOOL),
            'inventory_rule' => filled($row['inventory_rule'] ?? null) ? (string) $row['inventory_rule'] : 'inherit',
            'inventory_quantity' => filled($row['inventory_quantity'] ?? null) ? (int) $row['inventory_quantity'] : null,
            'coupon_override' => $couponEnabled ? [
                'enabled' => true,
                'coupon_code' => $row['coupon_code'] ?? null,
                'discount_type' => $row['coupon_discount_type'] ?? null,
                'discount_value' => isset($row['coupon_discount_value']) ? (float) $row['coupon_discount_value'] : null,
            ] : null,
        ];
    }

    private function resolveProductBySku(string $sku, ?Product $fallback): Product
    {
        if ($sku === '' && $fallback !== null) {
            return $fallback;
        }

        $product = Product::query()->where('sku', strtoupper($sku))->first();

        if ($product === null) {
            throw new InvalidArgumentException("Bill product SKU [{$sku}] was not found.");
        }

        return $product;
    }

    /**
     * @return array<string, mixed>
     */
    private function productCycleToPayload(ProductBillingCycle $cycle): array
    {
        return array_merge(
            ['id' => $cycle->id],
            BillingCycleAttributeMapper::fromProductCycle($cycle),
            [
                'bill_product_uuid' => $cycle->billProduct?->uuid,
                'bill_product_sku' => $cycle->billProduct?->sku,
                'variant_sku' => $cycle->variant?->sku,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function campaignCycleToPayload(CampaignProductBillingCycle $cycle): array
    {
        return array_merge(
            ['id' => $cycle->id],
            BillingCycleAttributeMapper::fromCampaignCycle($cycle),
            [
                'bill_product_uuid' => $cycle->product?->uuid,
                'bill_product_sku' => $cycle->product?->sku,
                'variant_sku' => $cycle->variant?->sku,
            ],
        );
    }

    /**
     * @return list<string|null>
     */
    private function productCycleToCsvRow(ProductBillingCycle $cycle): array
    {
        $coupon = $cycle->coupon_override ?? [];

        return [
            (string) $cycle->cycle_number,
            $cycle->billProduct?->sku,
            $cycle->variant?->sku,
            $cycle->price !== null ? (string) $cycle->price : null,
            $cycle->shipping_price !== null ? (string) $cycle->shipping_price : null,
            (string) $cycle->bill_delay_days,
            $cycle->delay_fulfillment ? '1' : '0',
            $cycle->shippable ? '1' : '0',
            $cycle->warehouse,
            $cycle->subscription_enabled ? '1' : '0',
            $cycle->repeat_forever ? '1' : '0',
            $cycle->inventory_rule?->value,
            $cycle->inventory_quantity !== null ? (string) $cycle->inventory_quantity : null,
            ($coupon['enabled'] ?? false) ? '1' : '0',
            $coupon['coupon_code'] ?? null,
            $coupon['discount_type'] ?? null,
            isset($coupon['discount_value']) ? (string) $coupon['discount_value'] : null,
        ];
    }

    /**
     * @return list<string|null>
     */
    private function campaignCycleToCsvRow(CampaignProduct $line, CampaignProductBillingCycle $cycle): array
    {
        $coupon = $cycle->coupon_override ?? [];

        return [
            (string) $line->id,
            $line->product?->sku,
            (string) $cycle->cycle_number,
            $cycle->product?->sku,
            $cycle->variant?->sku,
            $cycle->price !== null ? (string) $cycle->price : null,
            $cycle->shipping_price !== null ? (string) $cycle->shipping_price : null,
            (string) $cycle->bill_delay_days,
            $cycle->delay_fulfillment ? '1' : '0',
            $cycle->shippable ? '1' : '0',
            $cycle->warehouse,
            $cycle->subscription_enabled ? '1' : '0',
            $cycle->repeat_forever ? '1' : '0',
            $cycle->inventory_rule?->value,
            $cycle->inventory_quantity !== null ? (string) $cycle->inventory_quantity : null,
            ($coupon['enabled'] ?? false) ? '1' : '0',
            $coupon['coupon_code'] ?? null,
            $coupon['discount_type'] ?? null,
            isset($coupon['discount_value']) ? (string) $coupon['discount_value'] : null,
        ];
    }

    /**
     * @param  list<list<string|null>>  $rows
     * @return array{path: string, filename: string, format: string}
     */
    private function writeCsv(string $filenamePrefix, array $headers, array $rows): array
    {
        $filename = sprintf('%s-%s.csv', $filenamePrefix, now()->format('Ymd-His'));
        $relativePath = 'exports/'.$filename;
        $absolutePath = storage_path('app/'.$relativePath);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0775, true);
        }

        $writer = WriterFactory::createFromFile($absolutePath);
        $writer->openToFile($absolutePath);
        $writer->addRow(Row::fromValues($headers));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return ['path' => $absolutePath, 'filename' => $filename, 'format' => 'csv'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{path: string, filename: string, format: string}
     */
    private function writeJson(string $filenamePrefix, array $payload): array
    {
        $filename = sprintf('%s-%s.json', $filenamePrefix, now()->format('Ymd-His'));
        $relativePath = 'exports/'.$filename;
        $absolutePath = storage_path('app/'.$relativePath);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0775, true);
        }

        file_put_contents($absolutePath, json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return ['path' => $absolutePath, 'filename' => $filename, 'format' => 'json'];
    }
}
