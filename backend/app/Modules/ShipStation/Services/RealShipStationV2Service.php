<?php

namespace App\Modules\ShipStation\Services;

use App\Modules\ShipStation\Contracts\ShipStationServiceInterface;
use App\Modules\ShipStation\Enums\ShipStationMode;
use App\Modules\ShipStation\Exceptions\ShipStationApiException;

/**
 * Real ShipStation V2 API service (API-Key header, no V1 Basic Auth).
 */
class RealShipStationV2Service implements ShipStationServiceInterface
{
    public function __construct(
        private readonly ShipStationV2ApiClient $v2,
    ) {}

    public function mode(): string
    {
        return ShipStationMode::Real->value;
    }

    public function testConnection(): bool
    {
        $this->v2->getCarriers();

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getCarriers(): array
    {
        $response = $this->v2->getCarriers();
        $carriers = $response['carriers'] ?? [];

        return collect($carriers)
            ->map(fn (array $carrier): array => [
                'name' => (string) ($carrier['friendly_name'] ?? $carrier['nickname'] ?? 'Carrier'),
                'code' => (string) ($carrier['carrier_code'] ?? ''),
                'carrier_id' => (string) ($carrier['carrier_id'] ?? ''),
                'accountNumber' => (string) ($carrier['account_number'] ?? ''),
                'requiresFundedAccount' => (bool) ($carrier['requires_funded_amount'] ?? false),
                'balance' => (float) ($carrier['balance'] ?? 0),
                'primary' => (bool) ($carrier['primary'] ?? false),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getServices(string $carrierCode): array
    {
        $response = $this->v2->getCarriers();
        $carriers = collect($response['carriers'] ?? []);

        $carrier = $carriers->first(fn (array $item): bool => ($item['carrier_code'] ?? '') === $carrierCode)
            ?? $carriers->first();

        if ($carrier === null) {
            return [];
        }

        return collect($carrier['services'] ?? [])
            ->map(fn (array $service): array => [
                'code' => (string) ($service['service_code'] ?? ''),
                'name' => (string) ($service['name'] ?? $service['service_code'] ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getStores(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload): array
    {
        throw new ShipStationApiException(
            'ShipStation V2 does not support V1 createOrder. Use the V2 shipment workflow instead.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrder(int $orderId): array
    {
        throw new ShipStationApiException('ShipStation V2 order lookup is not implemented via V1 order ID.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listOrders(array $filters = []): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createShipment(array $payload): array
    {
        return $this->v2->createShipments($payload);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listShipments(array $filters = []): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getShipment(int $shipmentId): array
    {
        return $this->v2->getShipment((string) $shipmentId);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public function getShippingRates(array $payload): array
    {
        throw new ShipStationApiException('ShipStation V2 rate shopping is not yet implemented.');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createLabel(array $payload): array
    {
        $shipmentId = (string) ($payload['shipmentId'] ?? $payload['shipment_id'] ?? '');

        if ($shipmentId === '') {
            throw new ShipStationApiException('shipmentId is required to create a label via ShipStation V2.');
        }

        unset($payload['shipmentId'], $payload['shipment_id']);

        return $this->v2->createLabelFromShipment($shipmentId, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function getLabel(int $shipmentId): array
    {
        return $this->v2->getLabel((string) $shipmentId);
    }

    public function voidLabel(int $labelId): array
    {
        throw new ShipStationApiException('ShipStation V2 void label is not yet implemented.');
    }

    public function cancelShipment(int $shipmentId): array
    {
        return $this->voidLabel($shipmentId);
    }
}
