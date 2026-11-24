<?php

namespace Tests\Feature\ShipStation;

use App\Models\Order;
use App\Models\ShipStationIntegration;
use App\Models\ShipStationWebhookReceipt;
use App\Modules\ShipStation\Jobs\SyncShipStationTrackingJob;
use App\Modules\ShipStation\Services\ShipStationV2ApiClient;
use App\Modules\ShipStation\Services\ShipStationWebhookDeliveryDedupService;
use App\Modules\ShipStation\Services\ShipStationWebhookIngress;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\ShipStation\Support\ShipStationPhase1TrackingSyncTestCase;

class ShipStationPhase1WebhookDedupTest extends ShipStationPhase1TrackingSyncTestCase
{
    #[Test]
    public function stable_webhook_id_skips_redelivery_when_prior_receipt_is_processed(): void
    {
        $this->enableGlobalTrackingWebhookConfig();
        Queue::fake();

        $order = $this->createGlobalTrackingOrder();
        $dedup = app(ShipStationWebhookDeliveryDedupService::class);
        $payload = $this->shipNotifyPayload($order, 'se-phase1-1', ['notification_id' => 'nid-100']);
        $fingerprint = $dedup->buildFingerprint($payload);

        ShipStationWebhookReceipt::query()->create([
            'delivery_fingerprint' => $fingerprint,
            'resource_type' => 'SHIP_NOTIFY',
            'status' => ShipStationWebhookReceipt::STATUS_PROCESSED,
            'order_id' => $order->id,
        ]);

        app(ShipStationWebhookIngress::class)->handle($payload);

        $this->assertDatabaseCount('shipstation_webhook_receipts', 1);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function stable_webhook_id_skips_redelivery_when_prior_receipt_is_processing(): void
    {
        $this->enableGlobalTrackingWebhookConfig();
        Queue::fake();

        $order = $this->createGlobalTrackingOrder();
        $dedup = app(ShipStationWebhookDeliveryDedupService::class);
        $payload = $this->shipNotifyPayload($order, 'se-phase1-1', ['webhook_id' => 'wh-200']);
        $fingerprint = $dedup->buildFingerprint($payload);

        ShipStationWebhookReceipt::query()->create([
            'delivery_fingerprint' => $fingerprint,
            'resource_type' => 'SHIP_NOTIFY',
            'status' => ShipStationWebhookReceipt::STATUS_PROCESSING,
            'order_id' => $order->id,
        ]);

        app(ShipStationWebhookIngress::class)->handle($payload);

        $this->assertDatabaseCount('shipstation_webhook_receipts', 1);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function failed_stable_id_receipt_does_not_block_a_new_global_delivery(): void
    {
        $this->enableGlobalTrackingWebhookConfig();
        Queue::fake();

        $order = $this->createGlobalTrackingOrder();
        $dedup = app(ShipStationWebhookDeliveryDedupService::class);
        $payload = $this->shipNotifyPayload($order, 'se-phase1-1', ['event_id' => 'evt-300']);
        $fingerprint = $dedup->buildFingerprint($payload);

        ShipStationWebhookReceipt::query()->create([
            'delivery_fingerprint' => $fingerprint,
            'resource_type' => 'SHIP_NOTIFY',
            'status' => ShipStationWebhookReceipt::STATUS_FAILED,
            'outbox_status' => 'pending',
            'order_id' => $order->id,
        ]);

        app(ShipStationWebhookIngress::class)->handle($payload);

        $this->assertDatabaseCount('shipstation_webhook_receipts', 2);
        Queue::assertPushed(SyncShipStationTrackingJob::class, 1);
    }

    #[Test]
    public function identical_body_hash_within_short_window_is_ignored_for_global_tracking(): void
    {
        $this->enableGlobalTrackingWebhookConfig();
        config(['shipstation.tracking_sync.delivery_dedup_ttl_seconds' => 120]);
        Queue::fake();

        $order = $this->createGlobalTrackingOrder();
        $payload = $this->shipNotifyPayload($order);

        app(ShipStationWebhookIngress::class)->handle($payload);
        app(ShipStationWebhookIngress::class)->handle($payload);

        $ignored = ShipStationWebhookReceipt::query()
            ->where('order_id', $order->id)
            ->where('status', ShipStationWebhookReceipt::STATUS_IGNORED)
            ->where('ignore_reason', 'duplicate_body_short_window')
            ->count();

        $this->assertSame(1, $ignored);
        Queue::assertPushed(SyncShipStationTrackingJob::class, 1);
    }

    #[Test]
    public function identical_body_at_exact_ttl_boundary_allows_second_dispatch(): void
    {
        $this->enableGlobalTrackingWebhookConfig();
        config(['shipstation.tracking_sync.delivery_dedup_ttl_seconds' => 2]);
        Queue::fake();

        $order = $this->createGlobalTrackingOrder();
        $payload = $this->shipNotifyPayload($order);
        $dedup = app(ShipStationWebhookDeliveryDedupService::class);
        $fingerprint = $dedup->buildFingerprint($payload);

        Carbon::setTestNow('2026-11-23 12:00:00');
        app(ShipStationWebhookIngress::class)->handle($payload);

        Carbon::setTestNow('2026-11-23 12:00:02');
        Cache::forget('shipstation:webhook:dedup:'.$fingerprint);

        app(ShipStationWebhookIngress::class)->handle($payload);

        Carbon::setTestNow();

        Queue::assertPushed(SyncShipStationTrackingJob::class, 2);
    }

    #[Test]
    public function identical_body_after_short_window_ttl_allows_second_dispatch(): void
    {
        $this->enableGlobalTrackingWebhookConfig();
        config(['shipstation.tracking_sync.delivery_dedup_ttl_seconds' => 1]);
        Queue::fake();

        $order = $this->createGlobalTrackingOrder();
        $payload = $this->shipNotifyPayload($order);

        app(ShipStationWebhookIngress::class)->handle($payload);

        $dedup = app(ShipStationWebhookDeliveryDedupService::class);
        Cache::forget('shipstation:webhook:dedup:'.$dedup->buildFingerprint($payload));

        app(ShipStationWebhookIngress::class)->handle($payload);

        Queue::assertPushed(SyncShipStationTrackingJob::class, 2);
    }

    #[Test]
    public function legitimate_later_tracking_update_with_different_body_dispatches_again(): void
    {
        $this->enableGlobalTrackingWebhookConfig();
        Queue::fake();

        $order = $this->createGlobalTrackingOrder();

        app(ShipStationWebhookIngress::class)->handle($this->shipNotifyPayload($order, 'se-phase1-1', [
            'tracking_number' => '1Z111',
        ]));
        app(ShipStationWebhookIngress::class)->handle($this->shipNotifyPayload($order, 'se-phase1-1', [
            'tracking_number' => '1Z222',
        ]));

        Queue::assertPushed(SyncShipStationTrackingJob::class, 2);
    }

    #[Test]
    public function pilot_orders_are_not_blocked_by_global_body_hash_deduplication(): void
    {
        $pilotUuid = '6591d63b-f76a-4786-95ef-46fa27c62956';

        $this->enableGlobalTrackingWebhookConfig();
        config([
            'shipstation.pilot_auto_status_sync.enabled' => true,
            'shipstation.pilot_auto_status_sync.order_uuids' => [$pilotUuid],
        ]);
        $this->seedActiveShipStationSetting();

        $this->mock(ShipStationV2ApiClient::class, function ($mock): void {
            $mock->shouldReceive('getShipment')->andReturn([
                'shipment_id' => 'se-pilot-1',
                'shipment_status' => 'shipped',
                'tracking_number' => '1ZPILOT',
            ]);
        });

        $order = Order::factory()->create(['uuid' => $pilotUuid]);
        ShipStationIntegration::query()->create([
            'order_id' => $order->id,
            'provider' => 'shipstation',
            'external_shipment_id' => 'se-pilot-1',
            'status' => \App\Modules\ShipStation\Enums\ShipStationOrderStatus::Processing,
        ]);

        $payload = $this->shipNotifyPayload($order, 'se-pilot-1');

        app(ShipStationWebhookIngress::class)->handle($payload);
        app(ShipStationWebhookIngress::class)->handle($payload);

        $this->assertSame(
            2,
            ShipStationWebhookReceipt::query()
                ->where('order_id', $order->id)
                ->where('scope_type', 'pilot')
                ->where('status', ShipStationWebhookReceipt::STATUS_PROCESSED)
                ->count(),
        );
        $this->assertDatabaseMissing('shipstation_webhook_receipts', [
            'order_id' => $order->id,
            'ignore_reason' => 'duplicate_body_short_window',
        ]);
    }
}