<?php

namespace App\Modules\ShipStation\Services;

use App\Models\Order;
use App\Modules\ShipStation\Enums\ShipStationSyncStatus;
use App\Modules\ShipStation\Exceptions\ShipStationApiException;
use Illuminate\Support\Facades\Log;

/**
 * Rebill orders must never reach ShipStation shipment or label creation.
 *
 * Canonical detection: {@see Order::isRebill()} / {@see OrderType::Rebill}.
 */
class ShipStationRebillGuard
{
    public function isExcluded(Order $order): bool
    {
        return $order->isRebill();
    }

    public function markSyncSkipped(Order $order, string $context): Order
    {
        Log::info('ShipStation sync skipped — rebill orders are excluded from ShipStation.', [
            'order_id' => $order->id,
            'order_type' => $order->order_type->value,
            'context' => $context,
        ]);

        $order->update([
            'shipstation_status' => ShipStationSyncStatus::Skipped->value,
            'shipstation_sync_error' => null,
        ]);

        return $order->fresh();
    }

    public function assertAllowed(Order $order, string $context): void
    {
        if (! $this->isExcluded($order)) {
            return;
        }

        Log::warning('ShipStation operation blocked for rebill order.', [
            'order_id' => $order->id,
            'order_type' => $order->order_type->value,
            'context' => $context,
        ]);

        throw new ShipStationApiException('Rebill orders are excluded from ShipStation integration.');
    }
}
