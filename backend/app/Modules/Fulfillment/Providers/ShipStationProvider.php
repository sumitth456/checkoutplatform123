<?php

namespace App\Modules\Fulfillment\Providers;

use App\Models\Fulfillment;
use App\Modules\Fulfillment\Contracts\FulfillmentProviderInterface;
use App\Modules\Order\DTOs\WarehouseShipmentResponseDTO;
use App\Modules\Order\Enums\DeliveryStatus;
use App\Modules\Order\Enums\ShipmentStatus;

class ShipStationProvider implements FulfillmentProviderInterface
{
    public function submit(Fulfillment $fulfillment): WarehouseShipmentResponseDTO
    {
        return new WarehouseShipmentResponseDTO(
            warehouseReference: 'SS-'.$fulfillment->uuid,
            trackingNumber: '1Z'.strtoupper(substr(str_replace('-', '', $fulfillment->uuid), 0, 16)),
            carrier: 'ups',
            shipmentStatus: ShipmentStatus::Processing,
            deliveryStatus: DeliveryStatus::Pending,
            raw: ['provider' => 'shipstation', 'status' => 'submitted'],
        );
    }

    public function testConnection(): bool
    {
        return true;
    }
}
