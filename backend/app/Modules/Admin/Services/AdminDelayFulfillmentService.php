<?php

namespace App\Modules\Admin\Services;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Modules\Fulfillment\Services\FulfillmentOrchestratorService;
use App\Modules\Fulfillment\Services\FulfillmentTimelineService;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Modules\Order\Enums\OrderFulfillmentStatus;
use App\Modules\Order\Services\DelayFulfillmentEngineService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdminDelayFulfillmentService
{
    public function __construct(
        private readonly DelayFulfillmentEngineService $engine,
        private readonly FulfillmentOrchestratorService $orchestrator,
        private readonly FulfillmentTimelineService $timeline,
    ) {}

    /** @return array{scheduled: int, due_today: int, overdue: int, in_transit: int, failed: int, cancelled: int, all: int} */
    public function tabCounts(): array
    {
        $today = now()->toDateString();

        return [
            'scheduled' => Fulfillment::query()
                ->where('status', FulfillmentRecordStatus::Scheduled)
                ->whereDate('scheduled_ship_date', '>', $today)
                ->count(),
            'due_today' => Fulfillment::query()
                ->where('status', FulfillmentRecordStatus::Scheduled)
                ->whereDate('scheduled_ship_date', $today)
                ->count(),
            'overdue' => Fulfillment::query()
                ->where('status', FulfillmentRecordStatus::Scheduled)
                ->whereDate('scheduled_ship_date', '<', $today)
                ->count(),
            'in_transit' => Fulfillment::query()->whereIn('status', [
                FulfillmentRecordStatus::Submitted,
                FulfillmentRecordStatus::Shipped,
                FulfillmentRecordStatus::Delivered,
            ])->count(),
            'failed' => Fulfillment::query()
                ->where('status', FulfillmentRecordStatus::Failed)
                ->count(),
            'cancelled' => Fulfillment::query()
                ->where('status', FulfillmentRecordStatus::Cancelled)
                ->count(),
            'all' => Fulfillment::query()->count(),
        ];
    }

    public function cancel(Fulfillment $fulfillment, ?string $reason = null): Fulfillment
    {
        if (! in_array($fulfillment->status, [
            FulfillmentRecordStatus::Scheduled,
            FulfillmentRecordStatus::Pending,
            FulfillmentRecordStatus::Failed,
            FulfillmentRecordStatus::OnHold,
        ], true)) {
            throw new InvalidArgumentException('This fulfillment cannot be cancelled.');
        }

        return $this->orchestrator->cancel($fulfillment, $reason);
    }

    public function reschedule(Fulfillment $fulfillment, Carbon $shipDate): Fulfillment
    {
        if ($fulfillment->status !== FulfillmentRecordStatus::Scheduled) {
            throw new InvalidArgumentException('Only scheduled fulfillments can be rescheduled.');
        }

        $delayDays = max(0, (int) now()->startOfDay()->diffInDays($shipDate->copy()->startOfDay(), false));

        $fulfillment->update([
            'scheduled_ship_date' => $shipDate->toDateString(),
            'delay_days' => $delayDays,
        ]);

        $this->timeline->record($fulfillment, 'rescheduled', 'Ship Date Updated', 'New ship date: '.$shipDate->toDateString());

        return $fulfillment->fresh();
    }

    public function retryFailed(Fulfillment $fulfillment): Fulfillment
    {
        if ($fulfillment->status !== FulfillmentRecordStatus::Failed) {
            throw new InvalidArgumentException('Only failed fulfillments can be retried.');
        }

        $fulfillment->update([
            'status' => FulfillmentRecordStatus::Scheduled,
            'failure_reason' => null,
            'failed_at' => null,
        ]);

        $this->timeline->record($fulfillment, 'retried', 'Fulfillment Re-queued', 'Returned to scheduled queue');

        return $fulfillment->fresh();
    }

    public function forceSubmitToWarehouse(Fulfillment $fulfillment): Fulfillment
    {
        if (! in_array($fulfillment->status, [
            FulfillmentRecordStatus::Scheduled,
            FulfillmentRecordStatus::Pending,
            FulfillmentRecordStatus::Failed,
            FulfillmentRecordStatus::ReadyToShip,
        ], true)) {
            throw new InvalidArgumentException('This fulfillment cannot be submitted.');
        }

        if ($fulfillment->scheduled_ship_date->isFuture()) {
            $fulfillment->update(['scheduled_ship_date' => now()->toDateString()]);
        }

        return DB::transaction(function () use ($fulfillment): Fulfillment {
            $provider = $this->orchestrator->providerFor($fulfillment);
            $response = $provider->submit($fulfillment);

            $fulfillment->update([
                'warehouse_reference' => $response->warehouseReference,
                'tracking_number' => $response->trackingNumber,
                'carrier' => $response->carrier,
                'shipment_status' => $response->shipmentStatus,
                'delivery_status' => $response->deliveryStatus,
                'warehouse_response' => $response->raw,
                'status' => FulfillmentRecordStatus::Shipped,
                'shipped_at' => now(),
            ]);

            $this->timeline->record($fulfillment, 'submitted', 'Sent to Fulfillment House', 'Submitted via '.$fulfillment->fulfillmentHouse?->name);

            if ($fulfillment->order) {
                $fulfillment->order->update([
                    'fulfillment_status' => OrderFulfillmentStatus::Processing,
                ]);
            }

            return $fulfillment->fresh();
        });
    }

    public function processDueQueue(): int
    {
        return $this->engine->processDueFulfillments();
    }
}
