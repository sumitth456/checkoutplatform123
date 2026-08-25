<?php

namespace App\Modules\Order\Services;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Modules\Admin\Services\PlatformConfigResolver;
use App\Modules\Order\Contracts\FulfillmentRepositoryInterface;
use App\Modules\Order\Contracts\OrderRepositoryInterface;
use App\Modules\Order\Contracts\WarehouseApiClientInterface;
use App\Modules\Order\DTOs\WarehouseShipmentRequestDTO;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Modules\Order\Enums\OrderFulfillmentStatus;
use App\Modules\Order\Events\FulfillmentRecordCreated;
use App\Modules\Order\Events\FulfillmentSubmittedToWarehouse;
use App\Modules\Order\Events\OrderFulfillmentRequested;
use App\Modules\Order\Exceptions\WarehouseApiException;
use App\Modules\Order\Jobs\SubmitFulfillmentToWarehouseJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class DelayFulfillmentEngineService
{
    public function __construct(
        private readonly FulfillmentRepositoryInterface $fulfillments,
        private readonly OrderRepositoryInterface $orders,
        private readonly WarehouseApiClientInterface $warehouse,
        private readonly PlatformConfigResolver $platformConfig,
    ) {}

    public function createDelayedFulfillmentRecord(Order $order): Fulfillment
    {
        $existing = Fulfillment::query()->where('order_id', $order->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        $delayDays = $this->resolveDelayDays($order);
        $shipDate = now()->startOfDay()->addDays($delayDays);

        return DB::transaction(function () use ($order, $delayDays, $shipDate): Fulfillment {
            $fulfillment = $this->fulfillments->createForOrder($order, $delayDays, $shipDate);

            $this->orders->update($order, [
                'fulfillment_status' => OrderFulfillmentStatus::Scheduled,
            ]);

            Event::dispatch(new OrderFulfillmentRequested($order));
            Event::dispatch(new FulfillmentRecordCreated($fulfillment));

            return $fulfillment;
        });
    }

    public function processDueFulfillments(): int
    {
        $count = 0;

        foreach ($this->fulfillments->findDueForWarehouseSubmission() as $fulfillment) {
            SubmitFulfillmentToWarehouseJob::dispatch($fulfillment->id)
                ->onQueue($this->platformConfig->fulfillmentQueue());
            $count++;
        }

        return $count;
    }

    public function submitToWarehouse(Fulfillment $fulfillment): Fulfillment
    {
        if (! $fulfillment->isDueForWarehouseSubmission()) {
            return $fulfillment;
        }

        $order = $fulfillment->order;
        $request = WarehouseShipmentRequestDTO::fromOrder($order, $fulfillment);

        $fulfillment->warehouse_request = $request->toPayload();
        $fulfillment->save();

        try {
            $response = $this->warehouse->createShipment($request);
        } catch (WarehouseApiException $exception) {
            $fulfillment->status = FulfillmentRecordStatus::Failed;
            $fulfillment->failure_reason = $exception->getMessage();
            $fulfillment->save();

            throw $exception;
        }

        return DB::transaction(function () use ($fulfillment, $order, $response): Fulfillment {
            $fulfillment->warehouse_reference = $response->warehouseReference;
            $fulfillment->tracking_number = $response->trackingNumber;
            $fulfillment->carrier = $response->carrier;
            $fulfillment->shipment_status = $response->shipmentStatus;
            $fulfillment->delivery_status = $response->deliveryStatus;
            $fulfillment->warehouse_response = $response->raw;
            $fulfillment->status = FulfillmentRecordStatus::Submitted;
            $fulfillment->shipped_at = now();
            $fulfillment->save();

            $orderStatus = $response->deliveryStatus->value === 'delivered'
                ? OrderFulfillmentStatus::Fulfilled
                : OrderFulfillmentStatus::Processing;

            $this->orders->update($order, [
                'fulfillment_status' => $orderStatus,
                'fulfilled_at' => $orderStatus === OrderFulfillmentStatus::Fulfilled ? now() : null,
            ]);

            Event::dispatch(new FulfillmentSubmittedToWarehouse($fulfillment->fresh(), $response));

            return $fulfillment->fresh();
        });
    }

    private function resolveDelayDays(Order $order): int
    {
        $fromMetadata = $order->metadata['fulfillment_delay_days'] ?? null;

        if ($fromMetadata !== null) {
            return max(0, (int) $fromMetadata);
        }

        return $this->platformConfig->fulfillmentDefaultDelayDays();
    }
}
