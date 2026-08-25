<?php

namespace Tests\Unit\Admin;

use App\Models\WarehouseSetting;
use App\Modules\Admin\Services\WarehouseAdminService;
use App\Modules\Order\Enums\WarehouseConnectionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WarehouseAdminServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_test_connection_marks_healthy_on_successful_health_check(): void
    {
        Http::fake([
            'https://warehouse.test/api/health' => Http::response(['ok' => true], 200),
        ]);

        $setting = WarehouseSetting::query()->create([
            'name' => 'Primary warehouse',
            'base_url' => 'https://warehouse.test/api',
            'api_key' => 'key',
            'timeout_seconds' => 10,
            'create_shipment_path' => '/v1/shipments',
            'is_active' => true,
        ]);

        $updated = app(WarehouseAdminService::class)->testConnection($setting);

        $this->assertSame(WarehouseConnectionStatus::Healthy, $updated->connection_status);
        $this->assertNotNull($updated->last_tested_at);
    }

    public function test_shipment_tab_counts_include_failed_warehouse_submissions(): void
    {
        \App\Models\Fulfillment::query()->create([
            'order_id' => \App\Models\Order::factory()->create()->id,
            'delay_days' => 1,
            'scheduled_ship_date' => now()->toDateString(),
            'status' => \App\Modules\Order\Enums\FulfillmentRecordStatus::Failed,
            'warehouse_request' => ['order_uuid' => 'test'],
        ]);

        $counts = app(WarehouseAdminService::class)->shipmentTabCounts();

        $this->assertSame(1, $counts['failed']);
        $this->assertSame(1, $counts['all']);
    }
}
