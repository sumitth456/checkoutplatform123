<?php

namespace App\Modules\ShipStation\Support;

use App\Models\Order;

final class ShipStationV2OrderReference
{
    /** CRM order number used as ShipStation external identifiers for new shipments. */
    public static function crmExternalReference(Order $order): string
    {
        $number = trim((string) $order->order_number);

        if ($number === '') {
            throw new \InvalidArgumentException('Order must have an order_number before ShipStation shipment creation.');
        }

        return $number;
    }

    public static function legacyExternalShipmentId(Order $order): string
    {
        return 'cp-shipment-'.$order->id;
    }

    public static function legacyExternalOrderId(Order $order): string
    {
        return (string) config('shipstation.order_key_prefix', 'cp-order-').$order->id;
    }
}
