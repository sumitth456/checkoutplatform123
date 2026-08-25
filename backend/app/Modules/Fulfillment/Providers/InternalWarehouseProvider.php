<?php

namespace App\Modules\Fulfillment\Providers;

use App\Models\Fulfillment;
use App\Modules\Fulfillment\Contracts\FulfillmentProviderInterface;
use App\Modules\Order\DTOs\WarehouseShipmentRequestDTO;
use App\Modules\Order\DTOs\WarehouseShipmentResponseDTO;
use App\Modules\Order\Enums\DeliveryStatus;
use App\Modules\Order\Enums\ShipmentStatus;
use App\Modules\Order\Services\DelayFulfillmentEngineService;

class InternalWarehouseProvider implements FulfillmentProviderInterface
{
    public function __construct(
        private readonly DelayFulfillmentEngineService $engine,
    ) {}

    public function submit(Fulfillment $fulfillment): WarehouseShipmentResponseDTO
    {
        $updated = $this->engine->submitToWarehouse($fulfillment);

        return new WarehouseShipmentResponseDTO(
            warehouseReference: $updated->warehouse_reference ?? 'INT-'.$updated->uuid,
            trackingNumber: $updated->tracking_number ?? 'TRK'.strtoupper(substr($updated->uuid, 0, 8)),
            carrier: $updated->carrier ?? 'internal',
            shipmentStatus: $updated->shipment_status ?? ShipmentStatus::Processing,
            deliveryStatus: $updated->delivery_status ?? DeliveryStatus::Pending,
            raw: $updated->warehouse_response ?? [],
        );
    }

    public function testConnection(): bool
    {
        return true;
    }
}
