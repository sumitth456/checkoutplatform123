<?php

namespace Tests\Feature\ShipStation\Support;

use App\Models\Order;
use App\Models\ShipStationIntegration;
use App\Models\ShipStationSetting;
use App\Modules\ShipStation\Enums\ShipStationOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

abstract class ShipStationPhase1TrackingSyncTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    protected function enableGlobalTrackingWebhookConfig(): void
    {
        config([
            'shipstation.mode' => 'mock',
            'shipstation.webhook_secret' => 'test-webhook-secret-value',
            'shipstation.tracking_sync.enabled' => true,
            'shipstation.tracking_sync.shadow_mode' => true,
            'shipstation.tracking_sync.fulfillment_updates' => false,
            'shipstation.tracking_sync.dispatch_recovery_enabled' => false,
            'shipstation.pilot_auto_status_sync.enabled' => false,
            'shipstation.pilot_auto_status_sync.order_uuids' => [],
        ]);
    }

    protected function seedActiveShipStationSetting(): void
    {
        ShipStationSetting::query()->updateOrCreate(
            ['name' => 'ShipStation'],
            [
                'is_active' => true,
                'api_key' => 'test-key',
                'base_url' => 'https://api.shipstation.com/v2',
                'timeout_seconds' => 30,
            ],
        );
    }

    protected function createGlobalTrackingOrder(string $shipmentId = 'se-phase1-1'): Order
    {
        $order = Order::factory()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
        ]);

        ShipStationIntegration::query()->create([
            'order_id' => $order->id,
            'provider' => 'shipstation',
            'external_shipment_id' => $shipmentId,
            'status' => ShipStationOrderStatus::Processing,
            'tracking_number' => null,
        ]);

        return $order;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function shipNotifyPayload(Order $order, string $shipmentId = 'se-phase1-1', array $extra = []): array
    {
        return array_merge([
            'resource_type' => 'SHIP_NOTIFY',
            'resource_url' => 'https://api.shipstation.com/v2/shipments/'.$shipmentId,
            'shipment_id' => $shipmentId,
        ], $extra);
    }
}