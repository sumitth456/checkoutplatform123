<?php

namespace Tests\Feature\ShipStation;

use App\Models\Fulfillment;
use App\Models\ShipStationIntegrationLog;
use App\Models\ShipStationWebhookReceipt;
use App\Modules\PayPal\Listeners\SyncPayPalTrackingListener;
use App\Modules\ShipStation\Jobs\SyncShipStationTrackingJob;
use App\Modules\ShipStation\Services\ShipStationSyncCoordinator;
use App\Modules\ShipStation\Services\ShipStationTrackingSyncService;
use App\Modules\ShipStation\Services\ShipStationV2ApiClient;
use App\Modules\ShipStation\Services\ShipStationWebhookIngress;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\ShipStation\Support\ShipStationPhase1TrackingSyncTestCase;

class ShipStationPhase1PayPalAndOperationsTest extends ShipStationPhase1TrackingSyncTestCase
{
    #[Test]
    public function paypal_tracking_registration_is_limited_to_warehouse_submission_event_in_provider(): void
    {
        $providerSource = file_get_contents(base_path('app/Modules/PayPal/PayPalServiceProvider.php')) ?: '';
        $this->assertStringContainsString('FulfillmentSubmittedToWarehouse::class', $providerSource);
        $this->assertStringContainsString(SyncPayPalTrackingListener::class, $providerSource);
        $this->assertStringNotContainsString('SyncShipStationTrackingJob', $providerSource);

        foreach ([
            'ShipStationTrackingSyncService.php',
            'ShipStationWebhookIngress.php',
            'ShipStationV2RemoteShipmentSyncApplier.php',
        ] as $relativePath) {
            $source = file_get_contents(base_path('app/Modules/ShipStation/Services/'.$relativePath)) ?: '';
            $this->assertStringNotContainsString('SyncPayPalTracking', $source, $relativePath);
            $this->assertStringNotContainsString('FulfillmentSubmittedToWarehouse', $source, $relativePath);
        }
    }

    #[Test]
    public function process_failure_after_successful_dispatch_marks_receipt_failed(): void
    {
        config([
            'shipstation.mode' => 'real',
            'shipstation.api_key' => 'test-key',
            'shipstation.tracking_sync.enabled' => true,
            'shipstation.tracking_sync.shadow_mode' => true,
        ]);
        $this->seedActiveShipStationSetting();

        $order = $this->createGlobalTrackingOrder('se-after-commit-fail');

        $this->mock(ShipStationV2ApiClient::class, function ($mock): void {
            $mock->shouldReceive('getShipment')->once()->andThrow(new \RuntimeException('downstream_after_commit'));
        });

        $receipt = ShipStationWebhookReceipt::query()->create([
            'order_id' => $order->id,
            'delivery_fingerprint' => 'after-commit-fail',
            'resource_type' => 'SHIP_NOTIFY',
            'status' => ShipStationWebhookReceipt::STATUS_DISPATCHED,
            'outbox_status' => 'sent',
        ]);

        try {
            (new SyncShipStationTrackingJob($receipt->id))->handle(app(ShipStationTrackingSyncService::class));
            $this->fail('Expected permanent failure exception');
        } catch (\RuntimeException) {
        }

        $receipt->refresh();
        $this->assertSame(ShipStationWebhookReceipt::STATUS_FAILED, $receipt->status);
        $this->assertSame('sent', $receipt->outbox_status);
    }

    #[Test]
    public function ingress_records_webhook_received_and_duplicate_delivery_logs_without_secrets(): void
    {
        $this->enableGlobalTrackingWebhookConfig();
        Queue::fake();

        $order = $this->createGlobalTrackingOrder();
        config(['shipstation.api_key' => 'secret-shipstation-key']);

        app(ShipStationWebhookIngress::class)->handle(
            $this->shipNotifyPayload($order, 'se-phase1-1', ['notification_id' => 'log-nid-1']),
        );

        $received = ShipStationIntegrationLog::query()
            ->where('request_type', 'webhook_SHIP_NOTIFY')
            ->latest('id')
            ->first();
        $this->assertNotNull($received);
        $this->assertStringNotContainsString('secret-shipstation-key', json_encode($received->context) ?: '');

        $dedup = app(\App\Modules\ShipStation\Services\ShipStationWebhookDeliveryDedupService::class);
        $payloadDup = $this->shipNotifyPayload($order, 'se-phase1-1', ['notification_id' => 'log-nid-dup']);
        $fingerprint = $dedup->buildFingerprint($payloadDup);

        ShipStationWebhookReceipt::query()->create([
            'delivery_fingerprint' => $fingerprint,
            'resource_type' => 'SHIP_NOTIFY',
            'status' => ShipStationWebhookReceipt::STATUS_PROCESSED,
            'order_id' => $order->id,
        ]);

        app(ShipStationWebhookIngress::class)->handle($payloadDup);

        $duplicate = ShipStationIntegrationLog::query()
            ->where('request_type', 'webhook_duplicate_delivery')
            ->latest('id')
            ->first();
        $this->assertNotNull($duplicate);
        $this->assertStringNotContainsString('secret-shipstation-key', json_encode($duplicate->context) ?: '');
    }

    #[Test]
    public function tracking_receipt_transitions_cover_processing_and_processed_states(): void
    {
        config([
            'shipstation.mode' => 'real',
            'shipstation.api_key' => 'test-key',
            'shipstation.tracking_sync.enabled' => true,
            'shipstation.tracking_sync.shadow_mode' => true,
        ]);
        $this->seedActiveShipStationSetting();

        $order = $this->createGlobalTrackingOrder('se-states');

        $this->mock(ShipStationV2ApiClient::class, function ($mock): void {
            $mock->shouldReceive('getShipment')->once()->andReturn([
                'shipment_id' => 'se-states',
                'shipment_status' => 'shipped',
                'tracking_number' => '1ZSTATE',
            ]);
        });

        $receipt = ShipStationWebhookReceipt::query()->create([
            'order_id' => $order->id,
            'delivery_fingerprint' => 'state-fp',
            'status' => ShipStationWebhookReceipt::STATUS_DISPATCHED,
            'resource_type' => 'SHIP_NOTIFY',
        ]);

        (new SyncShipStationTrackingJob($receipt->id))->handle(app(ShipStationTrackingSyncService::class));

        $receipt->refresh();
        $this->assertSame(ShipStationWebhookReceipt::STATUS_PROCESSED, $receipt->status);
        $this->assertNotNull($receipt->processing_started_at);
        $this->assertNotNull($receipt->processed_at);
    }

    #[Test]
    public function per_order_lock_is_released_after_successful_shadow_sync(): void
    {
        config([
            'shipstation.mode' => 'real',
            'shipstation.api_key' => 'test-key',
            'shipstation.tracking_sync.enabled' => true,
            'shipstation.tracking_sync.shadow_mode' => true,
            'cache.default' => 'database',
        ]);
        $this->seedActiveShipStationSetting();

        if (! \Illuminate\Support\Facades\Schema::hasTable('cache')) {
            $this->markTestSkipped('Database cache table not available for lock integration assertion.');
        }

        $order = $this->createGlobalTrackingOrder('se-lock-ok');

        $this->mock(ShipStationV2ApiClient::class, function ($mock): void {
            $mock->shouldReceive('getShipment')->once()->andReturn([
                'shipment_id' => 'se-lock-ok',
                'shipment_status' => 'shipped',
            ]);
        });

        $receipt = ShipStationWebhookReceipt::query()->create([
            'order_id' => $order->id,
            'delivery_fingerprint' => 'lock-ok-fp',
            'status' => ShipStationWebhookReceipt::STATUS_DISPATCHED,
            'resource_type' => 'SHIP_NOTIFY',
        ]);

        app(ShipStationTrackingSyncService::class)->syncReceipt($receipt);

        $lock = app(ShipStationSyncCoordinator::class)->lockForOrder($order->id);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    #[Test]
    public function shadow_tracking_does_not_mutate_fulfillment_records(): void
    {
        config([
            'shipstation.mode' => 'real',
            'shipstation.api_key' => 'test-key',
            'shipstation.tracking_sync.enabled' => true,
            'shipstation.tracking_sync.shadow_mode' => true,
            'shipstation.tracking_sync.fulfillment_updates' => false,
        ]);
        $this->seedActiveShipStationSetting();

        $order = $this->createGlobalTrackingOrder('se-fulfill-shadow');

        Fulfillment::query()->create([
            'order_id' => $order->id,
            'status' => \App\Modules\Order\Enums\FulfillmentRecordStatus::Scheduled,
            'scheduled_ship_date' => now()->toDateString(),
            'tracking_number' => null,
        ]);

        $beforeUpdatedAt = $order->fulfillments()->first()?->updated_at;

        $this->mock(ShipStationV2ApiClient::class, function ($mock): void {
            $mock->shouldReceive('getShipment')->once()->andReturn([
                'shipment_id' => 'se-fulfill-shadow',
                'shipment_status' => 'shipped',
                'tracking_number' => '1ZFULFILL',
            ]);
        });

        $receipt = ShipStationWebhookReceipt::query()->create([
            'order_id' => $order->id,
            'delivery_fingerprint' => 'fulfill-shadow',
            'status' => ShipStationWebhookReceipt::STATUS_DISPATCHED,
            'resource_type' => 'SHIP_NOTIFY',
        ]);

        (new SyncShipStationTrackingJob($receipt->id))->handle(app(ShipStationTrackingSyncService::class));

        $fulfillment = $order->fulfillments()->first();
        $this->assertNotNull($fulfillment);
        $this->assertNull($fulfillment->tracking_number);
        $this->assertTrue($fulfillment->updated_at->equalTo($beforeUpdatedAt));
    }
}