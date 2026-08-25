<?php

namespace Tests\Unit\Order;

use App\Models\WarehouseSetting;
use App\Modules\Order\Services\WarehouseConfigResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseConfigResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_database_settings_when_active(): void
    {
        WarehouseSetting::query()->create([
            'name' => 'Primary warehouse',
            'base_url' => 'https://warehouse.test/api',
            'api_key' => 'secret-key',
            'timeout_seconds' => 15,
            'create_shipment_path' => '/v2/shipments',
            'is_active' => true,
        ]);

        $config = app(WarehouseConfigResolver::class)->resolve();

        $this->assertSame('https://warehouse.test/api', $config['base_url']);
        $this->assertSame('secret-key', $config['api_key']);
        $this->assertSame('/v2/shipments', $config['create_shipment_path']);
    }
}
