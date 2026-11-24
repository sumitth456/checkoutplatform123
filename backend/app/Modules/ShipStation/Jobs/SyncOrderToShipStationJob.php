<?php

namespace App\Modules\ShipStation\Jobs;

use App\Models\Order;
use App\Modules\ShipStation\Enums\ShipStationSyncStatus;
use App\Modules\ShipStation\Exceptions\ShipStationApiException;
use App\Modules\ShipStation\Services\ShipStationAutomaticProcessingHoldService;
use App\Modules\ShipStation\Services\ShipStationOrderEligibilityService;
use App\Modules\ShipStation\Services\ShipStationOrderSyncExclusionService;
use App\Modules\ShipStation\Services\ShipStationOrderSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncOrderToShipStationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 120, 300, 600, 900];

    public function __construct(public readonly int $orderId)
    {
        $this->onQueue((string) config('shipstation.queue', config('checkout.fulfillment_queue', 'default')));
    }

    public function handle(
        ShipStationOrderSyncService $sync,
        ShipStationOrderEligibilityService $eligibility,
        ShipStationAutomaticProcessingHoldService $automaticProcessingHold,
        ShipStationOrderSyncExclusionService $syncExclusion,
    ): void {
        $order = Order::query()->find($this->orderId);

        if ($order === null) {
            return;
        }

        if ($syncExclusion->isExcluded($order)) {
            Log::info('ShipStation sync job skipped — order is excluded from synchronization.', [
                'order_id' => $order->id,
                'order_uuid' => $order->uuid,
                'order_number' => $order->order_number,
            ]);

            return;
        }

        if ($automaticProcessingHold->holdsAutomaticProcessing($order)) {
            Log::info('ShipStation sync job skipped — order is on automatic processing hold.', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return;
        }

        if ($order->isRebill()) {
            Log::info('ShipStation sync job skipped — rebill orders are excluded from ShipStation.', [
                'order_id' => $order->id,
            ]);

            return;
        }

        if (! $eligibility->isEligibleForShipStationSubmission($order)) {
            $eligibility->markSubmissionSkipped($order, $eligibility->evaluate($order));

            return;
        }

        $order->update([
            'shipstation_status' => ShipStationSyncStatus::Pending->value,
        ]);

        try {
            $sync->sync($order);
        } catch (ShipStationApiException $exception) {
            if ($this->attempts() >= $this->tries) {
                Log::error('ShipStation sync exhausted retries', [
                    'order_id' => $this->orderId,
                    'message' => $exception->getMessage(),
                ]);
            }

            throw $exception;
        }
    }
}
