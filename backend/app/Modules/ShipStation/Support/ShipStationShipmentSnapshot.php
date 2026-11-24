<?php

namespace App\Modules\ShipStation\Support;

final class ShipStationShipmentSnapshot
{
    /**
     * @param  array<string, mixed>  $shipment
     * @return array<string, mixed>
     */
    public static function fromShipmentArray(array $shipment): array
    {
        return [
            'shipment_id' => (string) ($shipment['shipment_id'] ?? ''),
            'shipment_status' => (string) ($shipment['shipment_status'] ?? ''),
            'label_status' => (string) ($shipment['label_status'] ?? data_get($shipment, 'label.status', '')),
            'tracking_number' => (string) ($shipment['tracking_number'] ?? ''),
            'carrier_code' => (string) ($shipment['carrier_code'] ?? ''),
            'carrier_id' => (string) ($shipment['carrier_id'] ?? ''),
            'service_code' => (string) ($shipment['service_code'] ?? ''),
            'modified_at' => (string) ($shipment['modified_at'] ?? ''),
            'ship_date' => (string) ($shipment['ship_date'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $previous
     * @param  array<string, mixed>  $incoming
     */
    public static function isUnchanged(?array $previous, array $incoming): bool
    {
        if ($previous === null || $previous === []) {
            return false;
        }

        $current = self::fromShipmentArray($incoming);

        foreach ($current as $key => $b) {
            $a = (string) ($previous[$key] ?? '');

            if ($a !== (string) $b) {
                return false;
            }
        }

        return true;
    }
}