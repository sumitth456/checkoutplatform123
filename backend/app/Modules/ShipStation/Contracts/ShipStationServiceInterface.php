<?php

namespace App\Modules\ShipStation\Contracts;

interface ShipStationServiceInterface
{
    public function mode(): string;

    public function testConnection(): bool;

    /**
     * @return list<array<string, mixed>>
     */
    public function getCarriers(): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function getServices(string $carrierCode): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function getStores(): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload): array;

    /**
     * @return array<string, mixed>
     */
    public function getOrder(int $orderId): array;

    /**
     * @param  array<string, mixed>  $params
     * @return list<array<string, mixed>>
     */
    public function listOrders(array $params = []): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createShipment(array $payload): array;

    /**
     * @return array<string, mixed>
     */
    public function getShipment(int $shipmentId): array;

    /**
     * @param  array<string, mixed>  $params
     * @return list<array<string, mixed>>
     */
    public function listShipments(array $params = []): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public function getShippingRates(array $payload): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createLabel(array $payload): array;

    /**
     * @return array<string, mixed>
     */
    public function getLabel(int $shipmentId): array;

    public function voidLabel(int $shipmentId): array;

    public function cancelShipment(int $shipmentId): array;
}
