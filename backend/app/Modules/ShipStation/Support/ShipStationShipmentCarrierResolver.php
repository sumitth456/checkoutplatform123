<?php

namespace App\Modules\ShipStation\Support;

use App\Models\ShippingCarrier;

/**
 * Normalizes ShipStation V2 carrier fields: carrier_code vs carrier_id (se-…).
 */
final class ShipStationShipmentCarrierResolver
{
    /**
     * @param  array<string, mixed>  $shipment
     * @return array{carrier_id: ?string, carrier_code: ?string, display_name: ?string}
     */
    public function resolveFromShipment(array $shipment): array
    {
        $carrierId = $this->normalizeId($shipment['carrier_id'] ?? null);
        $carrierCode = $this->normalizeCarrierCode($shipment['carrier_code'] ?? null);

        return [
            'carrier_id' => $carrierId,
            'carrier_code' => $carrierCode,
            'display_name' => $this->displayNameForCode($carrierCode),
        ];
    }

    public function normalizeCarrierCode(mixed $code): ?string
    {
        if (blank($code)) {
            return null;
        }

        $value = strtolower(trim((string) $code));

        if ($value === '' || $this->looksLikeShipStationResourceId($value)) {
            return null;
        }

        return $value;
    }

    public function normalizeId(mixed $id): ?string
    {
        if (blank($id)) {
            return null;
        }

        $value = trim((string) $id);

        return $value === '' ? null : $value;
    }

    public function looksLikeShipStationResourceId(string $value): bool
    {
        return preg_match('/^se-[a-z0-9]+$/i', $value) === 1;
    }

    public function sanitizeStoredCarrier(?string $stored): ?string
    {
        if (blank($stored)) {
            return null;
        }

        $value = trim((string) $stored);

        if ($this->looksLikeShipStationResourceId($value)) {
            return null;
        }

        return $value;
    }

    public function displayNameForCode(?string $carrierCode): ?string
    {
        $code = $this->normalizeCarrierCode($carrierCode);

        if ($code === null) {
            return null;
        }

        $name = ShippingCarrier::query()->where('code', $code)->value('name');

        if (is_string($name) && $name !== '') {
            return $name;
        }

        return strtoupper($code);
    }

    /**
     * @param  array<string, mixed>  $shipment
     */
    public function fulfillmentCarrierFromShipment(array $shipment, ?string $existing = null): ?string
    {
        $resolved = $this->resolveFromShipment($shipment);

        if ($resolved['carrier_code'] !== null) {
            return $resolved['carrier_code'];
        }

        return $this->sanitizeStoredCarrier($existing);
    }
}