<?php

namespace App\Modules\Fulfillment\Services;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Modules\Order\Enums\FulfillmentRecordStatus;

class FulfillmentTrackingImportService
{
    public function __construct(
        private readonly FulfillmentOrchestratorService $orchestrator,
        private readonly FulfillmentTimelineService $timeline,
    ) {}

    /**
     * @return array{updated: int, skipped: int, errors: list<string>}
     */
    public function importFromCsv(string $csvContent): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csvContent)) ?: [];
        $updated = 0;
        $skipped = 0;
        $errors = [];

        if ($lines === []) {
            return compact('updated', 'skipped', 'errors');
        }

        $headers = str_getcsv(array_shift($lines) ?: '');
        $headers = array_map(fn (string $h): string => strtolower(trim($h)), $headers);

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $row = str_getcsv($line);
            $data = array_combine($headers, array_pad($row, count($headers), null));

            if ($data === false) {
                $errors[] = 'Row '.($index + 2).': invalid CSV format';

                continue;
            }

            $orderNumber = $data['order_number'] ?? $data['orderid'] ?? $data['order_id'] ?? null;
            $tracking = $data['tracking_number'] ?? $data['trackingnumber'] ?? null;

            if (! $orderNumber || ! $tracking) {
                $skipped++;

                continue;
            }

            $order = Order::query()->where('order_number', $orderNumber)->first();
            if ($order === null) {
                $errors[] = 'Row '.($index + 2).": order {$orderNumber} not found";

                continue;
            }

            $fulfillment = Fulfillment::query()
                ->where('order_id', $order->id)
                ->latest('id')
                ->first();

            if ($fulfillment === null) {
                $errors[] = 'Row '.($index + 2).": no fulfillment for order {$orderNumber}";

                continue;
            }

            $this->orchestrator->updateTracking(
                $fulfillment,
                (string) $tracking,
                $data['carrier'] ?? null,
                $data['method'] ?? $data['shipping_method'] ?? null,
            );

            $updated++;
        }

        return compact('updated', 'skipped', 'errors');
    }

    public function processReturn(string $orderNumber, string $status, ?string $rmaNumber = null): ?Fulfillment
    {
        $order = Order::query()->where('order_number', $orderNumber)->first();
        if ($order === null) {
            return null;
        }

        $fulfillment = Fulfillment::query()->where('order_id', $order->id)->latest('id')->first();
        if ($fulfillment === null) {
            return null;
        }

        $normalized = strtoupper(trim($status));

        if ($normalized === 'RETURNED') {
            $fulfillment->update([
                'status' => FulfillmentRecordStatus::Returned,
                'returned_at' => now(),
                'rma_number' => $rmaNumber,
            ]);
            $this->timeline->record($fulfillment, 'returned', 'Order Returned', 'Return processed via CSV import');
        }

        if ($normalized === 'CANCELLED') {
            $this->orchestrator->cancel($fulfillment, 'Cancelled via return import');
        }

        return $fulfillment->fresh();
    }
}
