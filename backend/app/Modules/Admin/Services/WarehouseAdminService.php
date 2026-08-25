<?php

namespace App\Modules\Admin\Services;

use App\Models\Fulfillment;
use App\Models\WarehouseSetting;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Modules\Order\Enums\WarehouseConnectionStatus;
use Illuminate\Support\Facades\Http;

class WarehouseAdminService
{
    public function testConnection(WarehouseSetting $setting): WarehouseSetting
    {
        $baseUrl = rtrim((string) $setting->base_url, '/');
        $healthUrl = $baseUrl.'/health';

        try {
            $response = Http::timeout((int) $setting->timeout_seconds)
                ->withToken((string) $setting->api_key)
                ->get($healthUrl);

            if ($response->successful()) {
                $setting->update([
                    'connection_status' => WarehouseConnectionStatus::Healthy,
                    'last_tested_at' => now(),
                    'last_test_error' => null,
                ]);
            } else {
                $setting->update([
                    'connection_status' => WarehouseConnectionStatus::Unhealthy,
                    'last_tested_at' => now(),
                    'last_test_error' => 'HTTP '.$response->status(),
                ]);
            }
        } catch (\Throwable $exception) {
            $setting->update([
                'connection_status' => WarehouseConnectionStatus::Unhealthy,
                'last_tested_at' => now(),
                'last_test_error' => $exception->getMessage(),
            ]);
        }

        return $setting->fresh();
    }

    /** @return array{all: int, failed: int, shipped: int, pending: int} */
    public function shipmentTabCounts(): array
    {
        return [
            'all' => Fulfillment::query()->whereNotNull('warehouse_request')->count(),
            'failed' => Fulfillment::query()->where('status', FulfillmentRecordStatus::Failed)->count(),
            'shipped' => Fulfillment::query()->whereIn('status', [
                FulfillmentRecordStatus::Shipped,
                FulfillmentRecordStatus::InTransit,
                FulfillmentRecordStatus::Delivered,
            ])->count(),
            'pending' => Fulfillment::query()->whereIn('status', [
                FulfillmentRecordStatus::Scheduled,
                FulfillmentRecordStatus::Pending,
                FulfillmentRecordStatus::Queued,
            ])->count(),
        ];
    }
}
