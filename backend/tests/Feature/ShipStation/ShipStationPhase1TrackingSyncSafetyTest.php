<?php

namespace Tests\Feature\ShipStation;

use App\Models\Order;
use App\Models\ShipStationIntegration;
use App\Models\ShipStationSetting;
use App\Models\ShipStationWebhookReceipt;
use App\Modules\ShipStation\Enums\ShipStationOrderStatus;
use App\Modules\ShipStation\Jobs\SyncShipStationTrackingJob;
use App\Modules\ShipStation\Jobs\SyncOrderToShipStationJob;
use App\Modules\ShipStation\Services\ShipStationTrackingSyncService;
use App\Modules\ShipStation\Services\ShipStationV2ApiClient;
use App\Modules\ShipStation\Services\ShipStationV2ReadOnlyClient;
use App\Modules\ShipStation\Support\ShipStationTrackingSyncGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Modules\Order\Events\FulfillmentSubmittedToWarehouse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShipStationPhase1TrackingSyncSafetyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function tracking_read_only_client_exposes_get_endpoints_only(): void
    {
        $methods = array_map(
            fn (\ReflectionMethod $method) => $method->getName(),
            (new \ReflectionClass(ShipStationV2ReadOnlyClient::class))->getMethods(\ReflectionMethod::IS_PUBLIC),
        );
        $methods = array_values(array_filter($methods, fn (string $name) => $name !== '__construct'));
        sort($methods);
        $this->assertSame(['getLabel', 'getShipment'], $methods);

        foreach ($methods as $name) {
            $this->assertStringStartsWith('get', $name);
        }
    }

    #[Test]
    public function shadow_tracking_sync_does_not_mutate_integration_or_dispatch_order_sync_job(): void
    {
        config([
            'shipstation.mode' => 'real',
            'shipstation.api_key' => 'test-key',
            'shipstation.tracking_sync.enabled' => true,
            'shipstation.tracking_sync.shadow_mode' => true,
            'shipstation.tracking_sync.fulfillment_updates' => false,
        ]);

        ShipStationSetting::query()->updateOrCreate(
            ['name' => 'ShipStation'],
            [
                'is_active' => true,
                'api_key' => 'test-key',
                'base_url' => 'https://api.shipstation.com/v2',
                'timeout_seconds' => 30,
            ],
        );

        Queue::fake();
        Event::fake();

        $order = Order::factory()->create();
        ShipStationIntegration::query()->create([
            'order_id' => $order->id,
            'provider' => 'shipstation',
            'external_shipment_id' => 'se-readonly-1',
            'status' => ShipStationOrderStatus::Processing,
            'tracking_number' => null,
        ]);

        $this->mock(ShipStationV2ApiClient::class, function ($mock) use ($order): void {
            $mock->shouldReceive('getShipment')
                ->once()
                ->with('se-readonly-1', $order->id)
                ->andReturn([
                    'shipment_id' => 'se-readonly-1',
                    'shipment_status' => 'shipped',
                    'tracking_number' => '1Z999',
                ]);
            $mock->shouldNotReceive('createShipments');
            $mock->shouldNotReceive('updateShipment');
            $mock->shouldNotReceive('createLabelFromShipment');
        });

        $receipt = ShipStationWebhookReceipt::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'order_id' => $order->id,
            'status' => ShipStationWebhookReceipt::STATUS_DISPATCHED,
            'delivery_fingerprint' => 'test-fp',
            'resource_type' => 'SHIP_NOTIFY',
        ]);

        $result = app(ShipStationTrackingSyncService::class)->syncReceipt($receipt);

        $this->assertTrue($result['shadow'] ?? false);

        $order->shipStationIntegration->refresh();
        $this->assertNull($order->shipStationIntegration->tracking_number);

        Queue::assertNotPushed(SyncOrderToShipStationJob::class);
        Event::assertNotDispatched(FulfillmentSubmittedToWarehouse::class);
    }

    #[Test]
    public function global_tracking_disabled_by_default_and_independent_from_pilot_config(): void
    {
        $gate = app(ShipStationTrackingSyncGate::class);

        config([
            'shipstation.pilot_auto_status_sync.enabled' => true,
            'shipstation.tracking_sync.enabled' => false,
        ]);

        $this->assertFalse($gate->isEnabled());
        $this->assertTrue($gate->isShadowMode());
        $this->assertFalse($gate->mayUpdateFulfillment());
        $this->assertFalse($gate->mayCallMarkDelivered());
    }

    #[Test]
    public function global_webhook_queues_tracking_job_without_order_sync_job(): void
    {
        config([
            'shipstation.mode' => 'mock',
            'shipstation.webhook_secret' => 'test-webhook-secret-value',
            'shipstation.tracking_sync.enabled' => true,
            'shipstation.tracking_sync.shadow_mode' => true,
        ]);

        Queue::fake();

        $order = Order::factory()->create(['uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee']);
        ShipStationIntegration::query()->create([
            'order_id' => $order->id,
            'provider' => 'shipstation',
            'external_shipment_id' => 'se-queue-1',
            'status' => ShipStationOrderStatus::Processing,
        ]);

        $response = $this->postJson('/api/v1/webhooks/shipstation?token=test-webhook-secret-value', [
            'resource_type' => 'SHIP_NOTIFY',
            'resource_url' => 'https://api.shipstation.com/v2/shipments/se-queue-1',
            'shipment_id' => 'se-queue-1',
        ]);

        $response->assertOk();

        Queue::assertPushed(SyncShipStationTrackingJob::class);
        Queue::assertNotPushed(SyncOrderToShipStationJob::class);
    }
}