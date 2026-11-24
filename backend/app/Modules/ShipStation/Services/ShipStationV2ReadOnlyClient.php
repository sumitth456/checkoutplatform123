<?php

namespace App\Modules\ShipStation\Services;

/**
 * GET-only surface for tracking synchronization (no shipment/label writes).
 */
final class ShipStationV2ReadOnlyClient
{
    public function __construct(
        private readonly ShipStationV2ApiClient $v2,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getShipment(string $shipmentId, ?int $orderId = null): array
    {
        return $this->v2->getShipment($shipmentId, $orderId);
    }

    /**
     * @return array<string, mixed>
     */
    public function getLabel(string $labelId, ?int $orderId = null): array
    {
        return $this->v2->getLabel($labelId, $orderId);
    }
}