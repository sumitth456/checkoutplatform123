<?php

namespace App\Modules\ShipStation\Services;

use App\Models\Order;
use App\Modules\ShipStation\Exceptions\ShipStationApiException;
use App\Modules\ShipStation\Exceptions\ShipStationSubmissionSkippedException;
use App\Modules\ShipStation\Support\ShipStationApiErrorFormatter;
use App\Modules\ShipStation\Support\ShipStationApiLogger;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShipStationV2ApiClient
{
    public function __construct(
        private readonly ShipStationConfigResolver $config,
        private readonly ShipStationApiLogger $logger,
        private readonly ShipStationOrderEligibilityService $eligibility,
    ) {}

    public function baseUrl(bool $envOnly = false): string
    {
        if ($this->config->isMockMode()) {
            return rtrim((string) config('shipstation.mock_base_url', 'https://docs.shipstation.com/_mock/apis/openapi'), '/');
        }

        return rtrim($this->config->resolve(null, $envOnly)['base_url'], '/');
    }

    public function apiKey(bool $envOnly = false): string
    {
        if ($this->config->isMockMode()) {
            return (string) config('shipstation.mock_api_key', 'SSAT_MOCK_KEY');
        }

        return (string) $this->config->resolve(null, $envOnly)['api_key'];
    }

    /**
     * Read-only connection test for real V2 API.
     *
     * @return array<string, mixed>
     */
    public function getAccountSettings(bool $envOnly = false): array
    {
        return $this->request('GET', '/account/settings', null, null, $envOnly);
    }

    /**
     * List warehouses (read-only). Useful before shipment creation — not required for connection test.
     *
     * @return array<string, mixed>
     */
    public function listWarehouses(bool $envOnly = false): array
    {
        return $this->request('GET', '/warehouses', null, null, $envOnly);
    }

    /**
     * @return array<string, mixed>
     */
    public function getCarriers(bool $envOnly = false): array
    {
        return $this->request('GET', '/carriers', null, null, $envOnly);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createShipments(array $payload, ?int $orderId = null): array
    {
        $this->assertOrderEligibleForMutation($orderId);

        return $this->request('POST', '/shipments', $payload, $orderId);
    }

    /**
     * @return array<string, mixed>
     */
    public function getShipment(string $shipmentId, ?int $orderId = null): array
    {
        return $this->request('GET', '/shipments/'.$shipmentId, null, $orderId);
    }

    /**
     * @return array<string, mixed>
     */
    public function getShipmentByExternalShipmentId(string $externalShipmentId, ?int $orderId = null): array
    {
        return $this->request(
            'GET',
            '/shipments/external_shipment_id/'.rawurlencode($externalShipmentId),
            null,
            $orderId,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateShipment(string $shipmentId, array $payload, ?int $orderId = null): array
    {
        return $this->request('PUT', '/shipments/'.$shipmentId, $payload, $orderId);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createLabelFromShipment(string $shipmentId, array $payload = [], ?int $orderId = null): array
    {
        $this->assertOrderEligibleForMutation($orderId);

        return $this->request('POST', '/labels/shipment/'.$shipmentId, $payload, $orderId);
    }

    private function assertOrderEligibleForMutation(?int $orderId): void
    {
        if ($orderId === null) {
            return;
        }

        $order = Order::query()->find($orderId);

        if ($order === null) {
            return;
        }

        $decision = $this->eligibility->evaluate($order);

        if (! $decision->eligible) {
            $this->eligibility->markSubmissionSkipped($order, $decision);

            throw new ShipStationSubmissionSkippedException($decision, action: 'shipment');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getLabel(string $labelId, ?int $orderId = null): array
    {
        return $this->request('GET', '/labels/'.$labelId.'?label_download_type=url', null, $orderId);
    }

    /**
     * @return array<string, mixed>
     */
    public function getLabelTracking(string $labelId, ?int $orderId = null): array
    {
        return $this->request('GET', '/labels/'.$labelId.'/track', null, $orderId);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function listLabels(array $query = [], ?int $orderId = null, bool $envOnly = false): array
    {
        return $this->request('GET', '/labels', null, $orderId, $envOnly, $query);
    }

    /**
     * @return array<string, mixed>
     */
    public function getLabelByExternalShipmentId(string $externalShipmentId, ?int $orderId = null): array
    {
        return $this->request(
            'GET',
            '/labels/external_shipment_id/'.rawurlencode($externalShipmentId),
            null,
            $orderId,
        );
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function request(
        string $method,
        string $path,
        ?array $payload = null,
        ?int $orderId = null,
        bool $envOnly = false,
        array $query = [],
    ): array {
        if (! $this->config->isMockMode() && blank($this->apiKey($envOnly))) {
            throw new ShipStationApiException('ShipStation API key is not configured.');
        }

        $url = $this->baseUrl($envOnly).$this->normalizePath($path);

        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        $timeout = (int) config('shipstation.timeout_seconds', 30);
        $attempt = 0;
        $maxAttempts = 3;

        while (true) {
            $attempt++;

            $pending = Http::timeout($timeout)
                ->acceptJson()
                ->withHeaders([
                    'API-Key' => $this->apiKey($envOnly),
                    'Content-Type' => 'application/json',
                ]);

            $response = match (strtoupper($method)) {
                'GET' => $pending->get($url),
                'POST' => $pending->post($url, $payload ?? []),
                'PUT' => $pending->put($url, $payload ?? []),
                'PATCH' => $pending->patch($url, $payload ?? []),
                'DELETE' => $pending->delete($url, $payload ?? []),
                default => throw new ShipStationApiException("Unsupported HTTP method: {$method}"),
            };

            if ($response->status() === 429 && $attempt < $maxAttempts) {
                $retryAfter = (int) ($response->header('Retry-After') ?? 1);
                Log::info('ShipStation API rate limited — retrying.', [
                    'path' => $path,
                    'attempt' => $attempt,
                    'retry_after_seconds' => max(1, $retryAfter),
                    'order_id' => $orderId,
                ]);
                sleep(max(1, $retryAfter));

                continue;
            }

            return $this->parseResponse($method, $path, $payload, $response, $orderId);
        }
    }

    private function normalizePath(string $path): string
    {
        $path = '/'.ltrim($path, '/');

        if ($this->config->isMockMode()) {
            return str_starts_with($path, '/v2/') ? $path : '/v2'.$path;
        }

        return str_starts_with($path, '/v2/') ? substr($path, 3) : $path;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function parseResponse(
        string $method,
        string $path,
        ?array $payload,
        Response $response,
        ?int $orderId,
    ): array {
        $body = $response->json();

        if (! is_array($body)) {
            $body = ['raw' => $response->body()];
        }

        $success = $response->successful() && ! $this->responseHasErrors($body);
        $errorMessage = $success ? null : $this->extractErrorMessage($body, $response->status());
        $logEvent = $this->resolveLogEvent($method, $path, $success);

        Log::info($logEvent, [
            'method' => strtoupper($method),
            'path' => $path,
            'http_status' => $response->status(),
            'success' => $success,
            'order_id' => $orderId,
            'mode' => $this->config->mode()->value,
        ]);

        $this->logger->log(
            method: $method,
            path: $path,
            request: $payload,
            response: $body,
            httpStatus: $response->status(),
            success: $success,
            errorMessage: $errorMessage,
            orderId: $orderId,
            context: [
                'base_url' => $this->baseUrl(),
                'api_version' => 'v2',
                'mock' => $this->config->isMockMode(),
            ],
        );

        if (! $success) {
            throw new ShipStationApiException($errorMessage, $response->status());
        }

        return $body;
    }

    private function resolveLogEvent(string $method, string $path, bool $success): string
    {
        if (str_contains($path, '/account/settings')) {
            return $success ? 'SHIPSTATION_CONNECTION_TEST' : 'SHIPSTATION_CONNECTION_TEST_FAILED';
        }

        if (str_contains($path, '/shipments') && strtoupper($method) === 'POST') {
            return $success ? 'SHIPSTATION_ORDER_CREATED' : 'SHIPSTATION_ORDER_FAILED';
        }

        if (str_contains($path, '/labels/shipment')) {
            return $success ? 'SHIPSTATION_FULFILLMENT_UPDATED' : 'SHIPSTATION_ORDER_FAILED';
        }

        if (str_contains($path, '/track')) {
            return $success ? 'SHIPSTATION_TRACKING_RECEIVED' : 'SHIPSTATION_ORDER_FAILED';
        }

        return $success ? 'SHIPSTATION_RESPONSE' : 'SHIPSTATION_REQUEST';
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function responseHasErrors(array $body): bool
    {
        if (($body['has_errors'] ?? false) === true) {
            return true;
        }

        if (isset($body['carriers']) || isset($body['label_id']) || isset($body['shipments'])) {
            return false;
        }

        if (isset($body['account_id']) || isset($body['company_name']) || isset($body['settings'])) {
            return false;
        }

        if (isset($body['errors']) && is_array($body['errors']) && $body['errors'] !== []) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractErrorMessage(array $body, int $status): string
    {
        return ShipStationApiErrorFormatter::format($status, $body);
    }
}
