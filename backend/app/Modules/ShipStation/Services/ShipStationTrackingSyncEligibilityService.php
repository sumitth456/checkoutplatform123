<?php

namespace App\Modules\ShipStation\Services;

use App\Models\Order;
use App\Modules\Order\Enums\OrderPaymentStatus;

final class ShipStationTrackingSyncEligibilityService
{
    public function __construct(
        private readonly ShipStationOrderSyncExclusionService $exclusions,
    ) {}

    public function isOrderEligibleForGlobalTracking(Order $order): bool
    {
        if ($this->exclusions->isExcluded($order)) {
            return false;
        }

        $integration = $order->shipStationIntegration;
        if ($integration === null || blank($integration->external_shipment_id)) {
            return false;
        }

        if (! (bool) config('shipstation.tracking_sync.include_refunded_orders', true)) {
            if (in_array($order->payment_status, [OrderPaymentStatus::Refunded, OrderPaymentStatus::PartiallyRefunded], true)) {
                return false;
            }
        }

        return true;
    }
}