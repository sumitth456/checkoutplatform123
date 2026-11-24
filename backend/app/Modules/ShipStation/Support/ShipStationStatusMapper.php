<?php

namespace App\Modules\ShipStation\Support;

use App\Modules\Order\Enums\FulfillmentRecordStatus;

class ShipStationStatusMapper
{
    public function mapShipmentStatus(?string $shipStatus): ?FulfillmentRecordStatus
    {
        return match (strtolower((string) $shipStatus)) {
            'shipped' => FulfillmentRecordStatus::Shipped,
            'label_purchased' => FulfillmentRecordStatus::Pending,
            'in_transit', 'out_for_delivery' => FulfillmentRecordStatus::InTransit,
            'delivered' => FulfillmentRecordStatus::Delivered,
            'cancelled', 'voided' => FulfillmentRecordStatus::Cancelled,
            'awaiting_shipment', 'pending' => FulfillmentRecordStatus::Pending,
            default => null,
        };
    }

    public function mapOrderStatus(?string $orderStatus): ?FulfillmentRecordStatus
    {
        return match (strtolower((string) $orderStatus)) {
            'shipped' => FulfillmentRecordStatus::Shipped,
            'cancelled', 'on_hold' => FulfillmentRecordStatus::Cancelled,
            'awaiting_shipment' => FulfillmentRecordStatus::Pending,
            default => null,
        };
    }
}
