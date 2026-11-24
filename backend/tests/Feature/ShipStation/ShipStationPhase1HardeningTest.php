<?php

namespace Tests\Feature\ShipStation;

use App\Models\ShipStationWebhookReceipt;
use App\Modules\ShipStation\Enums\ShipStationOrderStatus;
use App\Modules\ShipStation\Jobs\SyncShipStationTrackingJob;
use App\Modules\ShipStation\Services\ShipStationSyncCoordinator;
use App\Modules\ShipStation\Services\ShipStationTrackingJobDispatcher;
use App\Modules\ShipStation\Services\ShipStationTrackingSyncService;
use App\Modules\ShipStation\Services\ShipStationV2ApiClient;
use App\Modules\ShipStation\Services\ShipStationWebhookDeliveryDedupService;
use App\Modules\ShipStation\Services\ShipStationWebhookIngress;
use App\Modules\ShipStation\Services\ShipStationWebhookReceiptRecoveryService;
use App\Modules\ShipStation\Support\ShipStationTrackingSyncGate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\ShipStation\Support\ShipStationPhase1TrackingSyncTestCase;

class ShipStationPhase1HardeningTest extends ShipStationPhase1TrackingSyncTestCase
{
    #[Test]
    public function only_one_recovery_worker_can_claim_the_same_pending_receipt(): void
    {
        $order = $this->createGlobalTrackingOrder();
        $receipt = ShipStationWebhookReceipt::query()->create([
            'order_id' => $order->id,
            'delivery_fingerprint' => 'claim-race',
            'resource_type' => 'SHIP_NOTIFY',
            'status' => ShipStationWebhookReceipt::STATUS_RECEIVED,
            'outbox_status' => 'pending',
        ]);

        $service = app(ShipStationWebhookReceiptRecoveryService::class);

        $first = $service->tryClaimReceiptForRecovery($receipt->id);
        $second = $service->tryClaimReceiptForRecovery($receipt->id);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame('claiming', $first->fresh()->outbox_status);
    }

    #[Test]
    public function recovery_dispatch_failure_restores_pending_outbox_for_retry(): void
    {
        config(['shipstation.tracking_sync.dispatch_recovery_enabled' => true]);

        $this->mock(ShipStationTrackingJobDispatcher::class, function ($mock): void {
            $mock->shouldReceive('dispatchForReceipt')->once()->andThrow(new \RuntimeException('queue_down'));
        });

        $order = $this->createGlobalTrackingOrder();
        $receipt = ShipStationWebhookReceipt::query()->create([
            'order_id' => $order->id,
            'delivery_fingerprint' => 'recover-fail',
            'resource_type' => 'SHIP_NOTIFY',
            'status' => ShipStationWebhookReceipt::STATUS_FAILED,
            'outbox_status' => 'pending',
        ]);
        $receipt->forceFill(['created_at' => now()->subMinutes(10)])->saveQuietly();

        Queue::fake();

        $this->assertFalse(app(ShipStationWebhookReceiptRecoveryService::class)->recoverReceiptById($receipt->id));

        $receipt->refresh();
        $this->assertSame('pending', $receipt->outbox_status);
        $this->assertStringContainsString('queue_down', (string) $receipt->failure_reason);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function recovery_after_successful_retry_does_not_duplicate_job_dispatch(): void
    {
        config(['shipstation.tracking_sync.dispatch_recovery_enabled' => true]);

        $order = $this->createGlobalTrackingOrder();
        $receipt = ShipStationWebhookReceipt::query()->create([
            'order_id' => $order->id,
            'delivery_fingerprint' => 'recover-ok',
            'resource_type' => 'SHIP_NOTIFY',
            'status' => ShipStationWebhookReceipt::STATUS_RECEIVED,
            'outbox_status' => 'pending',
        ]);
        $receipt->forceFill(['created_at' => now()->subMinutes(10)])->saveQuietly();

        Queue::fake();

        Artisan::call('shipstation:recover-webhook-receipts', ['--minutes' => 1]);
        Artisan::call('shipstation:recover-webhook-receipts', ['--minutes' => 1]);

        Queue::assertPushed(SyncShipStationTrackingJob::class, 1);
    }

    #[Test]
    public function ingress_keeps_receipt_pending_when_dispatch_throws_after_commit(): void
    {
        $this->enableGlobalTrackingWebhookConfig();

        $this->mock(ShipStationTrackingJobDispatcher::class, function ($mock): void {
            $mock->shouldReceive('dispatchForReceipt')->once()->andThrow(new \RuntimeException('dispatch_broken'));
        });

        $order = $this->createGlobalTrackingOrder();
        app(ShipStationWebhookIngress::class)->handle($this->shipNotifyPayload($order));

        $receipt = ShipStationWebhookReceipt::query()->where('order_id', $order->id)->first();
        $this->assertNotNull($receipt);
        $this->assertSame(ShipStationWebhookReceipt::STATUS_RECEIVED, $receipt->status);
        $this->assertSame('pending', $receipt->outbox_status);
        $this->assertStringContainsString('dispatch_broken', (string) $receipt->failure_reason);
    }

    #[Test]
    public function ingress_dispatch_failure_can_be_recovered_later(): void
    {
        config([
            'shipstation.tracking_sync.dispatch_recovery_enabled' => true,
        ]);
        $this->enableGlobalTrackingWebhookConfig();

        $failingDispatcher = \Mockery::mock(ShipStationTrackingJobDispatcher::class);
        $failingDispatcher->shouldReceive('dispatchForReceipt')->once()->andThrow(new \RuntimeException('dispatch_broken'));
        $this->instance(ShipStationTrackingJobDispatcher::class, $failingDispatcher);
        $this->app->forgetInstance(ShipStationWebhookIngress::class);

        $order = $this->createGlobalTrackingOrder();
        app(ShipStationWebhookIngress::class)->handle($this->shipNotifyPayload($order));

        $receipt = ShipStationWebhookReceipt::query()->where('order_id', $order->id)->first();
        $this->assertNotNull($receipt);
        $receipt->forceFill(['created_at' => now()->subMinutes(10)])->saveQuietly();

        $this->app->instance(ShipStationTrackingJobDispatcher::class, new ShipStationTrackingJobDispatcher());
        $this->app->forgetInstance(ShipStationWebhookReceiptRecoveryService::class);

        Queue::fake();

        $recovered = app(ShipStationWebhookReceiptRecoveryService::class)->recoverStuckReceipts(1);
        $this->assertSame(1, $recovered);
        Queue::assertPushed(SyncShipStationTrackingJob::class, 1);

        $receipt->refresh();
        $this->assertSame(ShipStationWebhookReceipt::STATUS_DISPATCHED, $receipt->status);
        $this->assertSame('sent', $receipt->outbox_status);
    }

    #[Test]
    public function lock_expires_so_a_later_job_can_acquire_it(): void
    {
        config([
            'shipstation.tracking_sync.lock_seconds' => 1,
            'cache.default' => 'database',
        ]);

        if (! \Illuminate\Support\Facades\Schema::hasTable('cache')) {
            $this->markTestSkipped('Database cache table required for lock TTL test.');
        }

        $order = $this->createGlobalTrackingOrder('se-lock-ttl');
        $coordinator = app(ShipStationSyncCoordinator::class);

        $first = $coordinator->lockForOrder($order->id);
        $this->assertTrue($first->get());

        Carbon::setTestNow(now()->addSeconds(2));

        $second = $coordinator->lockForOrder($order->id);
        $this->assertTrue($second->get(), 'Lock should expire after configured TTL.');
        $second->release();

        Carbon::setTestNow();
    }

    #[Test]
    public function stable_webhook_id_with_dispatched_receipt_skips_duplicate_ingress(): void
    {
        $this->enableGlobalTrackingWebhookConfig();
        Queue::fake();

        $order = $this->createGlobalTrackingOrder();
        $dedup = app(ShipStationWebhookDeliveryDedupService::class);
        $payload = $this->shipNotifyPayload($order, 'se-phase1-1', ['webhook_id' => 'stable-dispatched']);
        $fingerprint = $dedup->buildFingerprint($payload);

        ShipStationWebhookReceipt::query()->create([
            'delivery_fingerprint' => $fingerprint,
            'resource_type' => 'SHIP_NOTIFY',
            'status' => ShipStationWebhookReceipt::STATUS_DISPATCHED,
            'outbox_status' => 'sent',
            'order_id' => $order->id,
        ]);

        app(ShipStationWebhookIngress::class)->handle($payload);

        $this->assertDatabaseCount('shipstation_webhook_receipts', 1);
        Queue::assertNothingPushed();
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function stableIdReceiptStatusMatrix(): array
    {
        return [
            'received' => [ShipStationWebhookReceipt::STATUS_RECEIVED, true],
            'dispatched' => [ShipStationWebhookReceipt::STATUS_DISPATCHED, true],
            'processing' => [ShipStationWebhookReceipt::STATUS_PROCESSING, true],
            'processed' => [ShipStationWebhookReceipt::STATUS_PROCESSED, true],
            'failed' => [ShipStationWebhookReceipt::STATUS_FAILED, false],
        ];
    }

    #[Test]
    #[DataProvider('stableIdReceiptStatusMatrix')]
    public function stable_id_duplicate_matrix(string $status, bool $shouldSkipDuplicate): void
    {
        $this->enableGlobalTrackingWebhookConfig();
        Queue::fake();

        $order = $this->createGlobalTrackingOrder('se-matrix');
        $dedup = app(ShipStationWebhookDeliveryDedupService::class);
        $payload = $this->shipNotifyPayload($order, 'se-matrix', ['event_id' => 'matrix-'.$status]);
        $fingerprint = $dedup->buildFingerprint($payload);

        ShipStationWebhookReceipt::query()->create([
            'delivery_fingerprint' => $fingerprint,
            'resource_type' => 'SHIP_NOTIFY',
            'status' => $status,
            'outbox_status' => $status === ShipStationWebhookReceipt::STATUS_FAILED ? 'pending' : 'sent',
            'order_id' => $order->id,
        ]);

        app(ShipStationWebhookIngress::class)->handle($payload);

        if ($shouldSkipDuplicate) {
            $this->assertDatabaseCount('shipstation_webhook_receipts', 1);
            Queue::assertNothingPushed();
        } else {
            $this->assertDatabaseCount('shipstation_webhook_receipts', 2);
            Queue::assertPushed(SyncShipStationTrackingJob::class, 1);
        }
    }

    #[Test]
    public function shadow_end_to_end_preserves_order_fulfillment_and_integration_columns(): void
    {
        config([
            'shipstation.mode' => 'real',
            'shipstation.api_key' => 'test-key',
            'shipstation.tracking_sync.enabled' => true,
            'shipstation.tracking_sync.shadow_mode' => true,
            'shipstation.tracking_sync.fulfillment_updates' => false,
        ]);
        $this->seedActiveShipStationSetting();

        $order = $this->createGlobalTrackingOrder('se-hardening-shadow');
        $integrationBefore = $order->shipStationIntegration->only([
            'tracking_number', 'status', 'external_shipment_id', 'tracking_sync_meta',
        ]);

        $this->mock(ShipStationV2ApiClient::class, function ($mock): void {
            $mock->shouldReceive('getShipment')->once()->andReturn([
                'shipment_id' => 'se-hardening-shadow',
                'shipment_status' => 'shipped',
                'tracking_number' => '1ZHARDEN',
            ]);
        });

        $receipt = ShipStationWebhookReceipt::query()->create([
            'order_id' => $order->id,
            'delivery_fingerprint' => 'hardening-shadow',
            'status' => ShipStationWebhookReceipt::STATUS_DISPATCHED,
            'resource_type' => 'SHIP_NOTIFY',
        ]);

        (new SyncShipStationTrackingJob($receipt->id))->handle(app(ShipStationTrackingSyncService::class));

        $order->refresh();
        $integrationAfter = $order->shipStationIntegration->only([
            'tracking_number', 'status', 'external_shipment_id', 'tracking_sync_meta',
        ]);

        $this->assertSame($integrationBefore['tracking_number'], $integrationAfter['tracking_number']);
        $this->assertSame($integrationBefore['status'], $integrationAfter['status']);
        $this->assertSame($integrationBefore['external_shipment_id'], $integrationAfter['external_shipment_id']);
        $this->assertNull($integrationAfter['tracking_number']);
        $this->assertArrayHasKey('shadow_last_snapshot', $integrationAfter['tracking_sync_meta'] ?? []);
        $this->assertSame('1ZHARDEN', data_get($integrationAfter['tracking_sync_meta'], 'shadow_last_snapshot.tracking_number'));
        $this->assertSame(ShipStationOrderStatus::Processing, $order->shipStationIntegration->status);
    }

    #[Test]
    public function tracking_sync_gate_defaults_remain_safe(): void
    {
        config([
            'shipstation.tracking_sync.enabled' => false,
            'shipstation.tracking_sync.shadow_mode' => true,
            'shipstation.tracking_sync.fulfillment_updates' => false,
            'shipstation.tracking_sync.dispatch_recovery_enabled' => false,
            'shipstation.pilot_auto_status_sync.order_uuids' => ['6591d63b-f76a-4786-95ef-46fa27c62956'],
        ]);

        $gate = app(ShipStationTrackingSyncGate::class);
        $this->assertFalse($gate->isEnabled());
        $this->assertTrue($gate->isShadowMode());
        $this->assertFalse($gate->mayUpdateFulfillment());
        $this->assertFalse((bool) config('shipstation.tracking_sync.dispatch_recovery_enabled'));
        $this->assertSame(['6591d63b-f76a-4786-95ef-46fa27c62956'], config('shipstation.pilot_auto_status_sync.order_uuids'));
    }
}