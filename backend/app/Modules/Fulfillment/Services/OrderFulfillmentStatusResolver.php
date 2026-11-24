<?php

namespace App\Modules\Fulfillment\Services;

use App\Models\Order;
use App\Modules\Order\Contracts\OrderRepositoryInterface;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Modules\Order\Enums\OrderFulfillmentStatus;

final class OrderFulfillmentStatusResolver
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
    ) {}

    public function refresh(Order $order): Order
    {
        $order->loadMissing('fulfillments');

        $fulfillments = $order->fulfillments;

        if ($fulfillments->isEmpty()) {
            return $order;
        }

        $parents = $fulfillments->whereNull('parent_fulfillment_id');
        $children = $fulfillments->whereNotNull('parent_fulfillment_id');

        $allDelivered = $fulfillments->every(
            fn ($f) => $f->status === FulfillmentRecordStatus::Delivered
        );

        if ($allDelivered) {
            $this->orders->update($order, [
                'fulfillment_status' => OrderFulfillmentStatus::Fulfilled,
                'fulfilled_at' => $order->fulfilled_at ?? now(),
            ]);

            return $order->fresh();
        }

        $parentComplete = $parents->isNotEmpty() && $parents->every(
            fn ($f) => in_array($f->status, [
                FulfillmentRecordStatus::Shipped,
                FulfillmentRecordStatus::Delivered,
                FulfillmentRecordStatus::InTransit,
            ], true)
        );

        $openChild = $children->contains(
            fn ($f) => ! in_array($f->status, [
                FulfillmentRecordStatus::Delivered,
                FulfillmentRecordStatus::Cancelled,
                FulfillmentRecordStatus::Failed,
            ], true)
        );

        $status = match (true) {
            $openChild || ($parentComplete && $children->isNotEmpty() && ! $allDelivered) => OrderFulfillmentStatus::Partial,
            $parentComplete => OrderFulfillmentStatus::Processing,
            default => $order->fulfillment_status,
        };

        if ($status !== $order->fulfillment_status) {
            $this->orders->update($order, [
                'fulfillment_status' => $status,
            ]);
        }

        return $order->fresh();
    }
}