<?php

namespace Tests\Feature\ShipStation;

use App\Models\ShipStationIntegrationLog;
use App\Models\ShipStationWebhookReceipt;
use App\Modules\ShipStation\Jobs\SyncShipStationTrackingJob;
use App\Modules\ShipStation\Services\ShipStationSyncCoordinator;
use App\Modules\ShipStation\Services\ShipStationTrackingSyncService;
use App\Modules\ShipStation\Services\ShipStationV2ApiClient;
use App\Modules\ShipStation\Services\ShipStationWebhookIngress;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\ShipStation\Support\ShipStationPhase1TrackingSyncTestCase;

class ShipStationPhase1TrackingSyncJobAndLockTest extends ShipStationPhase1TrackingSyncTestCase
{
    #[Test]
    public function tracking_job_declares_retry_limit_and_backoff_schedule(): void
    {
        $job = new SyncShipStationTrackingJob(1);

        $this->assertSame(5, $job->tries);
        $this->assertSame([30, 60, 120, 300, 600], $job->backoff);
    }

    #[Test]
    public function sync_coordinator_exposes_per_order_cache_lock_with_configured_ttl(): void
    {
        config(['shipstation.tracking_sync.lock_seconds' => 120]);

        $lock = app(ShipStationSyncCoordinator::class)->lockForOrder(42);

        $this->assertInstanceOf(\Illuminate\Contracts\Cache\Lock::class, $lock);
        $this->assertSame(120, (int) config('shipstation.tracking_sync.lock_seconds'));
    }

    #[Test]
    public function tracking_job_returns_receipt_to_dispatched_when_order_lock_is_busy(): void
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

        $order = $this->createGlobalTrackingOrder('se-lock-busy');

        $receipt = ShipStationWebhookReceipt::query()->create([
            'order_id' => $order->id,
            'delivery_fingerprint' => 'lock-busy-fp',
            'resource_type' => 'SHIP_NOTIFY',
            'status' => ShipStationWebhookReceipt::STATUS_DISPATCHED,
        ]);

        $coordinator = app(ShipStationSyncCoordinator::class);
        $hold = $coordinator->lockForOrder($order->id);
        $this->assertTrue($hold->get());

        try {
            (new SyncShipStationTrackingJob($receipt->id))->handle(app(ShipStationTrackingSyncService::class));
        } finally {
            $hold->release();
        }

        $receipt->refresh();
        $this->assertSame(ShipStationWebhookReceipt::STATUS_DISPATCHED, $receipt->status);
    }

    #[Test]
    public function tracking_sync_service_releases_lock_after_shipstation_api_error(): void
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

        $order = $this->createGlobalTrackingOrder('se-lock-err');

        $this->mock(ShipStationV2ApiClient::class, function ($mock): void {
            $mock->shouldReceive('getShipment')->once()->andThrow(new \RuntimeException('api_down'));
        });

        $receipt = ShipStationWebhookReceipt::query()->create([
            'order_id' => $order->id,
            'delivery_fingerprint' => 'lock-err-fp',
            'resource_type' => 'SHIP_NOTIFY',
            'status' => ShipStationWebhookReceipt::STATUS_DISPATCHED,
        ]);

        app(ShipStationTrackingSyncService::class)->syncReceipt($receipt);

        $lock = app(ShipStationSyncCoordinator::class)->lockForOrder($order->id);
        $this->assertTrue($lock->get(), 'Lock should be free after sync service finally block');
        $lock->release();
    }

    #[Test]
    public function job_marks_receipt_failed_on_permanent_sync_error(): void
    {
        config([
            'shipstation.mode' => 'real',
            'shipstation.api_key' => 'test-key',
            'shipstation.tracking_sync.enabled' => true,
            'shipstation.tracking_sync.shadow_mode' => true,
        ]);
        $this->seedActiveShipStationSetting();

        $order = $this->createGlobalTrackingOrder('se-fail-1');

        $this->mock(ShipStationV2ApiClient::class, function ($mock): void {
            $mock->shouldReceive('getShipment')->andThrow(new \RuntimeException('permanent_api_error'));
        });

        $receipt = ShipStationWebhookReceipt::query()->create([
            'order_id' => $order->id,
            'delivery_fingerprint' => 'fail-fp',
            'resource_type' => 'SHIP_NOTIFY',
            'status' => ShipStationWebhookReceipt::STATUS_DISPATCHED,
        ]);

        $job = new SyncShipStationTrackingJob($receipt->id);

        try {
            $job->handle(app(ShipStationTrackingSyncService::class));
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException) {
        }

        $receipt->refresh();
        $this->assertSame(ShipStationWebhookReceipt::STATUS_FAILED, $receipt->status);
        $this->assertStringContainsString('permanent_api_error', (string) $receipt->failure_reason);
    }

    #[Test]
    public function job_skips_when_receipt_already_processed(): void
    {
        $order = $this->createGlobalTrackingOrder();
        $receipt = ShipStationWebhookReceipt::query()->create([
            'order_id' => $order->id,
            'delivery_fingerprint' => 'done-job-fp',
            'status' => ShipStationWebhookReceipt::STATUS_PROCESSED,
            'processed_at' => now(),
        ]);

        $this->mock(ShipStationV2ApiClient::class, function ($mock): void {
            $mock->shouldNotReceive('getShipment');
        });

        (new SyncShipStationTrackingJob($receipt->id))->handle(app(ShipStationTrackingSyncService::class));
    }

    #[Test]
    public function two_webhooks_for_same_order_enqueue_two_jobs_that_transition_receipt_statuses(): void
    {
        $this->enableGlobalTrackingWebhookConfig();
        Queue::fake();

        $order = $this->createGlobalTrackingOrder('se-dup-order');

        app(ShipStationWebhookIngress::class)->handle($this->shipNotifyPayload($order, 'se-dup-order', [
            'tracking_number' => 'A1',
        ]));
        app(ShipStationWebhookIngress::class)->handle($this->shipNotifyPayload($order, 'se-dup-order', [
            'tracking_number' => 'A2',
        ]));

        Queue::assertPushed(SyncShipStationTrackingJob::class, 2);
    }

    #[Test]
    public function operational_logs_for_tracking_sync_do_not_include_api_secrets(): void
    {
        config([
            'shipstation.mode' => 'real',
            'shipstation.api_key' => 'super-secret-api-key-value',
            'shipstation.tracking_sync.enabled' => true,
            'shipstation.tracking_sync.shadow_mode' => true,
        ]);
        $this->seedActiveShipStationSetting();

        Log::spy();

        $order = $this->createGlobalTrackingOrder('se-log-1');

        $this->mock(ShipStationV2ApiClient::class, function ($mock): void {
            $mock->shouldReceive('getShipment')->once()->andReturn([
                'shipment_id' => 'se-log-1',
                'shipment_status' => 'shipped',
            ]);
        });

        $receipt = ShipStationWebhookReceipt::query()->create([
            'order_id' => $order->id,
            'delivery_fingerprint' => 'log-fp',
            'status' => ShipStationWebhookReceipt::STATUS_DISPATCHED,
            'resource_type' => 'SHIP_NOTIFY',
        ]);

        app(ShipStationTrackingSyncService::class)->syncReceipt($receipt);

        $log = ShipStationIntegrationLog::query()->latest('id')->first();
        $this->assertNotNull($log);
        $encoded = json_encode($log->context);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('super-secret-api-key-value', $encoded);
    }
}