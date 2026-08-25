<?php

namespace App\Modules\Order\Repositories;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Modules\Order\Contracts\FulfillmentRepositoryInterface;
use App\Modules\Order\Enums\DeliveryStatus;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Modules\Order\Enums\ShipmentStatus;
use Illuminate\Support\Collection;

class FulfillmentRepository implements FulfillmentRepositoryInterface
{
    public function createForOrder(Order $order, int $delayDays, \DateTimeInterface $scheduledShipDate): Fulfillment
    {
        return Fulfillment::query()->create([
            'order_id' => $order->id,
            'delay_days' => $delayDays,
            'scheduled_ship_date' => $scheduledShipDate,
            'status' => FulfillmentRecordStatus::Scheduled,
            'shipment_status' => ShipmentStatus::Pending,
            'delivery_status' => DeliveryStatus::Pending,
        ]);
    }

    public function findDueForWarehouseSubmission(): Collection
    {
        return Fulfillment::query()
            ->with(['order.items', 'order.customer'])
            ->where('status', FulfillmentRecordStatus::Scheduled)
            ->whereDate('scheduled_ship_date', now()->toDateString())
            ->get();
    }

    public function findByUuid(string $uuid): ?Fulfillment
    {
        return Fulfillment::query()->where('uuid', $uuid)->first();
    }

    public function lockForUpdate(int $fulfillmentId): ?Fulfillment
    {
        return Fulfillment::query()
            ->with(['order.items', 'order.customer'])
            ->whereKey($fulfillmentId)
            ->lockForUpdate()
            ->first();
    }
}
