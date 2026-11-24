<?php

namespace App\Modules\ShipStation\Services;

use App\Modules\ShipStation\Contracts\ShipStationServiceInterface;
use App\Modules\ShipStation\Enums\ShipStationMode;
use App\Modules\ShipStation\Exceptions\ShipStationApiException;
use App\Modules\ShipStation\Support\ShipStationApiLogger;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class RealShipStationService implements ShipStationServiceInterface
{
    public function __construct(
        private readonly ShipStationConfigResolver $config,
        private readonly ShipStationApiLogger $logger,
    ) {}

    public function mode(): string
    {
        return ShipStationMode::Live->value;
    }

    public function testConnection(): bool
    {
        $this->getCarriers();

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getCarriers(): array
    {
        $response = $this->request('GET', '/carriers');

        return is_array($response) ? $response : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getServices(string $carrierCode): array
    {
        $response = $this->request('GET', '/carriers/listservices', [
            'carrierCode' => $carrierCode,
        ]);

        return is_array($response) ? $response : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getStores(): array
    {
        $response = $this->request('GET', '/stores');

        return is_array($response) ? $response : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload): array
    {
        return $this->request('POST', '/orders/createorder', [], $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrder(int $orderId): array
    {
        return $this->request('GET', '/orders/'.$orderId);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<array<string, mixed>>
     */
    public function listOrders(array $params = []): array
    {
        $response = $this->request('GET', '/orders', $params);

        return is_array($response['orders'] ?? null) ? $response['orders'] : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createShipment(array $payload): array
    {
        return $this->request('POST', '/shipments/createshipment', [], $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function getShipment(int $shipmentId): array
    {
        $shipments = $this->listShipments(['shipmentId' => $shipmentId]);

        if ($shipments === []) {
            throw new ShipStationApiException('Shipment not found.', 404);
        }

        return $shipments[0];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<array<string, mixed>>
     */
    public function listShipments(array $params = []): array
    {
        $response = $this->request('GET', '/shipments', $params);

        return is_array($response['shipments'] ?? null) ? $response['shipments'] : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public function getShippingRates(array $payload): array
    {
        $response = $this->request('POST', '/shipments/getrates', [], $payload);

        return is_array($response) ? $response : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createLabel(array $payload): array
    {
        return $this->request('POST', '/shipments/createlabel', [], $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function getLabel(int $shipmentId): array
    {
        return $this->getShipment($shipmentId);
    }

    public function voidLabel(int $shipmentId): array
    {
        return $this->request('POST', '/shipments/voidlabel', [], [
            'shipmentId' => $shipmentId,
        ]);
    }

    public function cancelShipment(int $shipmentId): array
    {
        return $this->voidLabel($shipmentId);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        $settings = $this->config->resolve();

        if (! filled($settings['api_key']) || ! filled($settings['api_secret'])) {
            throw new ShipStationApiException('ShipStation API credentials are not configured.');
        }

        $client = $this->client($settings);
        $url = '/'.ltrim($path, '/');

        /** @var Response $response */
        $response = match (strtoupper($method)) {
            'GET' => $client->get($url, $query),
            'POST' => $client->post($url, $body ?? []),
            'PUT' => $client->put($url, $body ?? []),
            'DELETE' => $client->delete($url, $query),
            default => throw new ShipStationApiException('Unsupported HTTP method: '.$method),
        };

        if ($response->status() === 429) {
            $this->logger->log($method, $path, $body ?? $query, null, 429, false, 'Rate limit exceeded');

            throw new ShipStationApiException('ShipStation rate limit exceeded. Please retry shortly.', 429);
        }

        if ($response->failed()) {
            $message = $response->json('Message')
                ?? $response->json('message')
                ?? ('ShipStation API request failed with HTTP '.$response->status());

            $this->logger->log($method, $path, $body ?? $query, $response->json(), $response->status(), false, (string) $message);

            throw new ShipStationApiException((string) $message, $response->status(), $response->json() ?? []);
        }

        $json = $response->json();
        $payload = is_array($json) ? $json : [];

        $this->logger->log($method, $path, $body ?? $query, $payload, $response->status(), true);

        return $payload;
    }

    /**
     * @param  array{base_url: string, api_key: string, api_secret: string, timeout_seconds: int}  $settings
     */
    private function client(array $settings): PendingRequest
    {
        return Http::baseUrl(rtrim($settings['base_url'], '/'))
            ->withBasicAuth($settings['api_key'], $settings['api_secret'])
            ->acceptJson()
            ->asJson()
            ->timeout($settings['timeout_seconds']);
    }
}
