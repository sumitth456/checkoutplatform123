<?php

namespace App\Modules\ShipStation\Services;

use App\Models\Order;
use App\Modules\ShipStation\Exceptions\ShipStationAutomaticProcessingHoldException;

/**
 * Blocks automatic ShipStation sync, warehouse submission, and label workflows for specific orders.
 * Does not void shipments/labels in ShipStation. Manual admin ShipStation mutations are blocked via
 * {@see assertManualShipStationMutationAllowed()}.
 */
class ShipStationAutomaticProcessingHoldService
{
    public const HELD_ORDER_NUMBER = 'ORD-20261026-84C5D6';

    public function holdsAutomaticProcessing(Order $order): bool
    {
        $orderNumber = trim((string) ($order->order_number ?? ''));

        if ($orderNumber === '') {
            return false;
        }

        /** @var list<string> $held */
        $held = config('shipstation.automatic_processing_hold_order_numbers', []);

        return in_array($orderNumber, $held, true);
    }

    public function assertManualShipStationMutationAllowed(Order $order): void
    {
        if ($this->holdsAutomaticProcessing($order)) {
            throw new ShipStationAutomaticProcessingHoldException(trim((string) $order->order_number));
        }
    }
}
