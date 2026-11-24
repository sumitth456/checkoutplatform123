<?php

namespace Tests\Feature\ShipStation;

use App\Models\ShipStationWebhookReceipt;
use App\Modules\ShipStation\Jobs\SyncShipStationTrackingJob;
use App\Modules\ShipStation\Services\ShipStationWebhookIngress;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\ShipStation\Support\ShipStationPhase1TrackingSyncTestCase;

class ShipStationPhase1WebhookOutboxAndRecoveryTest extends ShipStationPhase1TrackingSyncTestCase
{
    #[Test]
    public function global_webhook_creates_receipt_with_received_status_and_pending_outbox(): void
    {
        $this->enableGlobalTrackingWebhookConfig();
        Queue::fake();

        $order = $this->createGlobalTrackingOrder();
        app(ShipStationWebhookIngress::class)->handle($this->shipNotifyPayload($order));

        $this->assertDatabaseHas('shipstation_webhook_receipts', [
            'order_id' => $order->id,
            'status' => ShipStationWebhookReceipt::STATUS_DISPATCHED,
            'outbox_status' => 'sent',
        ]);

        $receipt = ShipStationWebhookReceipt::query()->where('order_id', $order->id)->first();
        $this->assertNotNull($receipt);
        $this->assertNotNull($receipt->dispatched_at);
        $this->assertSame(1, $receipt->dispatch_attempts);
    }

    #[Test]
    public function tracking_job_dispatch_is_deferred_until_database_transaction_commits(): void
    {
        $this->enableGlobalTrackingWebhookConfig();
        Queue::fake();

        $order = $this->createGlobalTrackingOrder();

        DB::transaction(function () use ($order): void {
            app(ShipStationWebhookIngress::class)->handle($this->shipNotifyPayload($order));
            Queue::assertNothingPushed();
        });

        Queue::assertPushed(SyncShipStationTrackingJob::class, 1);
    }

    #[Test]
    public function rolled_back_transaction_does_not_dispatch_tracking_job(): void
    {
        $this->enableGlobalTrackingWebhookConfig();
        Queue::fake();

        $order = $this->createGlobalTrackingOrder();

        try {
            DB::transaction(function () use ($order): void {
                app(ShipStationWebhookIngress::class)->handle($this->shipNotifyPayload($order));
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }

        Queue::assertNothingPushed();
        $this->assertDatabaseMissing('shipstation_webhook_receipts', [
            'order_id' => $order->id,
        ]);
    }

    #[Test]
    public function recovery_command_is_disabled_by_default(): void
    {
        config(['shipstation.tracking_sync.dispatch_recovery_enabled' => false]);

        $order = $this->createGlobalTrackingOrder();
        ShipStationWebhookReceipt::query()->create([
            'order_id' => $order->id,
            'delivery_fingerprint' => 'stuck-fp',
            'resource_type' => 'SHIP_NOTIFY',
            'status' => ShipStationWebhookReceipt::STATUS_RECEIVED,
            'outbox_status' => 'pending',
            'created_at' => now()->subMinutes(10),
        ]);

        Queue::fake();

        $exit = Artisan::call('shipstation:recover-webhook-receipts');
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Dispatch recovery disabled', Artisan::output());
        Queue::assertNothingPushed();
    }

    #[Test]
    public function recovery_redispatches_stuck_receipts_when_enabled(): void
    {
        config(['shipstation.tracking_sync.dispatch_recovery_enabled' => true]);

        $order = $this->createGlobalTrackingOrder();
        $receipt = ShipStationWebhookReceipt::query()->create([
            'order_id' => $order->id,
            'delivery_fingerprint' => 'stuck-fp-2',
            'resource_type' => 'SHIP_NOTIFY',
            'status' => ShipStationWebhookReceipt::STATUS_FAILED,
            'outbox_status' => 'pending',
            'dispatch_attempts' => 1,
        ]);
        $receipt->forceFill(['created_at' => now()->subMinutes(10)])->saveQuietly();

        Queue::fake();

        Artisan::call('shipstation:recover-webhook-receipts', ['--minutes' => 1]);

        Queue::assertPushed(SyncShipStationTrackingJob::class, 1);

        $receipt->refresh();
        $this->assertSame(ShipStationWebhookReceipt::STATUS_DISPATCHED, $receipt->status);
        $this->assertSame('sent', $receipt->outbox_status);
        $this->assertSame(2, $receipt->dispatch_attempts);
    }

    #[Test]
    public function recovery_does_not_redispatch_already_processed_receipts(): void
    {
        config(['shipstation.tracking_sync.dispatch_recovery_enabled' => true]);

        $order = $this->createGlobalTrackingOrder();
        ShipStationWebhookReceipt::query()->create([
            'order_id' => $order->id,
            'delivery_fingerprint' => 'done-fp',
            'resource_type' => 'SHIP_NOTIFY',
            'status' => ShipStationWebhookReceipt::STATUS_PROCESSED,
            'outbox_status' => 'sent',
            'processed_at' => now(),
            'created_at' => now()->subMinutes(10),
        ]);

        Queue::fake();
        Artisan::call('shipstation:recover-webhook-receipts', ['--minutes' => 1]);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function concurrent_recovery_workers_do_not_duplicate_dispatch_for_same_receipt(): void
    {
        config(['shipstation.tracking_sync.dispatch_recovery_enabled' => true]);

        $order = $this->createGlobalTrackingOrder();
        $stuck = ShipStationWebhookReceipt::query()->create([
            'order_id' => $order->id,
            'delivery_fingerprint' => 'stuck-fp-3',
            'resource_type' => 'SHIP_NOTIFY',
            'status' => ShipStationWebhookReceipt::STATUS_RECEIVED,
            'outbox_status' => 'pending',
        ]);
        $stuck->forceFill(['created_at' => now()->subMinutes(10)])->saveQuietly();

        Queue::fake();

        Artisan::call('shipstation:recover-webhook-receipts', ['--minutes' => 1]);
        Artisan::call('shipstation:recover-webhook-receipts', ['--minutes' => 1]);

        Queue::assertPushed(SyncShipStationTrackingJob::class, 1);
    }
}