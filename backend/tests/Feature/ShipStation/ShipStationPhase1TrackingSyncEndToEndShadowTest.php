<?php

namespace Tests\Feature\ShipStation;

use App\Models\ShipStationIntegrationLog;
use App\Models\ShipStationWebhookReceipt;
use App\Modules\Order\Events\FulfillmentSubmittedToWarehouse;
use App\Modules\PayPal\Jobs\SyncPayPalTrackingJob;
use App\Modules\ShipStation\Enums\ShipStationOrderStatus;
use App\Modules\ShipStation\Jobs\SyncOrderToShipStationJob;
use App\Modules\ShipStation\Jobs\SyncShipStationTrackingJob;
use App\Modules\ShipStation\Services\ShipStationV2ApiClient;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\ShipStation\Support\ShipStationPhase1TrackingSyncTestCase;

class ShipStationPhase1TrackingSyncEndToEndShadowTest extends ShipStationPhase1TrackingSyncTestCase
{
    private const WEBHOOK_ENDPOINT = '/api/v1/webhooks/shipstation';

    #[Test]
    public function non_pilot_shadow_flow_records_would_apply_without_crm_or_payment_side_effects(): void
    {
        config([
            'shipstation.mode' => 'real',
            'shipstation.api_key' => 'test-key',
            'shipstation.webhook_secret' => 'test-webhook-secret-value',
            'shipstation.tracking_sync.enabled' => true,
            'shipstation.tracking_sync.shadow_mode' => true,
            'shipstation.tracking_sync.fulfillment_updates' => false,
            'shipstation.tracking_sync.dispatch_recovery_enabled' => false,
            'shipstation.pilot_auto_status_sync.enabled' => false,
        ]);

        $this->seedActiveShipStationSetting();

        Event::fake([FulfillmentSubmittedToWarehouse::class]);
        Queue::fake();

        $order = $this->createGlobalTrackingOrder('se-e2e-shadow');

        $this->mock(ShipStationV2ApiClient::class, function ($mock): void {
            $mock->shouldReceive('getShipment')
                ->once()
                ->with('se-e2e-shadow', \Mockery::type('int'))
                ->andReturn([
                    'shipment_id' => 'se-e2e-shadow',
                    'shipment_status' => 'shipped',
                    'label_status' => 'completed',
                    'tracking_number' => '1ZE2E999',
                    'carrier_id' => 'ups_walleted',
                ]);
            $mock->shouldNotReceive('createShipments');
            $mock->shouldNotReceive('updateShipment');
            $mock->shouldNotReceive('createLabelFromShipment');
        });

        $response = $this->postJson(self::WEBHOOK_ENDPOINT.'?token=test-webhook-secret-value', [
            'resource_type' => 'SHIP_NOTIFY',
            'resource_url' => 'https://api.shipstation.com/v2/shipments/se-e2e-shadow',
            'shipment_id' => 'se-e2e-shadow',
        ]);

        $response->assertOk();

        $receipt = ShipStationWebhookReceipt::query()->where('order_id', $order->id)->first();
        $this->assertNotNull($receipt);
        $this->assertSame(ShipStationWebhookReceipt::STATUS_DISPATCHED, $receipt->status);

        (new SyncShipStationTrackingJob($receipt->id))->handle(app(\App\Modules\ShipStation\Services\ShipStationTrackingSyncService::class));

        $receipt->refresh();
        $this->assertSame(ShipStationWebhookReceipt::STATUS_PROCESSED, $receipt->status);

        $order->shipStationIntegration->refresh();
        $this->assertNull($order->shipStationIntegration->tracking_number);
        $this->assertSame(ShipStationOrderStatus::Processing, $order->shipStationIntegration->status);
        $this->assertSame(
            '1ZE2E999',
            data_get($order->shipStationIntegration->tracking_sync_meta, 'shadow_last_snapshot.tracking_number'),
        );

        $shadowLog = ShipStationIntegrationLog::query()
            ->where('request_type', 'tracking_sync:shadow')
            ->where('order_id', $order->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($shadowLog);
        $this->assertArrayHasKey('would_apply', $shadowLog->context ?? []);
        $this->assertSame('1ZE2E999', data_get($shadowLog->context, 'would_apply.tracking_number'));

        Queue::assertNotPushed(SyncOrderToShipStationJob::class);
        Queue::assertNotPushed(SyncPayPalTrackingJob::class);
        Event::assertNotDispatched(FulfillmentSubmittedToWarehouse::class);
    }
}