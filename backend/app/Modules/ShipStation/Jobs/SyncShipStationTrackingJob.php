<?php

namespace App\Modules\ShipStation\Jobs;

use App\Models\ShipStationWebhookReceipt;
use App\Modules\ShipStation\Services\ShipStationTrackingSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncShipStationTrackingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 60, 120, 300, 600];

    public function __construct(public readonly int $receiptId)
    {
        $this->onQueue((string) config('shipstation.tracking_sync.queue', 'default'));
    }

    public function handle(ShipStationTrackingSyncService $sync): void
    {
        $receipt = ShipStationWebhookReceipt::query()->find($this->receiptId);

        if ($receipt === null) {
            return;
        }

        if (! in_array($receipt->status, [
            ShipStationWebhookReceipt::STATUS_DISPATCHED,
            ShipStationWebhookReceipt::STATUS_RECEIVED,
            ShipStationWebhookReceipt::STATUS_FAILED,
        ], true)) {
            return;
        }

        $receipt->update([
            'status' => ShipStationWebhookReceipt::STATUS_PROCESSING,
            'processing_started_at' => now(),
        ]);

        $result = $sync->syncReceipt($receipt);

        if (($result['retryable'] ?? false) === true) {
            $receipt->update(['status' => ShipStationWebhookReceipt::STATUS_DISPATCHED]);
            $this->release(30);

            return;
        }

        if (($result['ok'] ?? false) === false && ($result['skipped'] ?? false) !== true) {
            $receipt->update([
                'status' => ShipStationWebhookReceipt::STATUS_FAILED,
                'failure_reason' => (string) ($result['message'] ?? $result['reason'] ?? 'unknown'),
            ]);

            throw new \RuntimeException((string) ($result['message'] ?? $result['reason'] ?? 'tracking_sync_failed'));
        }

        $receipt->update([
            'status' => ShipStationWebhookReceipt::STATUS_PROCESSED,
            'processed_at' => now(),
        ]);
    }
}