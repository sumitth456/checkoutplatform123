<?php

namespace App\Modules\Order\DTOs;

use App\Modules\Order\Enums\DeliveryStatus;
use App\Modules\Order\Enums\ShipmentStatus;

readonly class WarehouseShipmentResponseDTO
{
    public function __construct(
        public string $warehouseReference,
        public string $trackingNumber,
        public string $carrier,
        public ShipmentStatus $shipmentStatus,
        public DeliveryStatus $deliveryStatus,
        public array $raw = [],
        public bool $shipmentOnly = false,
        public bool $skipCustomerNotification = false,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            warehouseReference: (string) ($data['reference'] ?? $data['id'] ?? ''),
            trackingNumber: (string) ($data['tracking_number'] ?? $data['tracking'] ?? ''),
            carrier: (string) ($data['carrier'] ?? 'unknown'),
            shipmentStatus: ShipmentStatus::tryFrom((string) ($data['shipment_status'] ?? 'shipped')) ?? ShipmentStatus::Shipped,
            deliveryStatus: DeliveryStatus::tryFrom((string) ($data['delivery_status'] ?? 'in_transit')) ?? DeliveryStatus::InTransit,
            raw: $data,
        );
    }
}
