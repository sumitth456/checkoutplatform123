<?php

namespace App\Modules\Fulfillment\Providers;

use App\Models\Fulfillment;
use App\Modules\Fulfillment\Contracts\FulfillmentProviderInterface;
use App\Modules\Order\DTOs\WarehouseShipmentResponseDTO;
use App\Modules\Order\Enums\DeliveryStatus;
use App\Modules\Order\Enums\ShipmentStatus;

class CsvExportProvider implements FulfillmentProviderInterface
{
    public function submit(Fulfillment $fulfillment): WarehouseShipmentResponseDTO
    {
        return new WarehouseShipmentResponseDTO(
            warehouseReference: 'CSV-'.$fulfillment->fulfillment_number,
            trackingNumber: null,
            carrier: null,
            shipmentStatus: ShipmentStatus::Pending,
            deliveryStatus: DeliveryStatus::Pending,
            raw: ['provider' => 'csv_export', 'exported_at' => now()->toIso8601String()],
        );
    }

    public function testConnection(): bool
    {
        return true;
    }
}
