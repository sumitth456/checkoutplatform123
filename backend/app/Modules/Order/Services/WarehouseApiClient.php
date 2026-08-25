<?php

namespace App\Modules\Order\Services;

use App\Modules\Order\Contracts\WarehouseApiClientInterface;
use App\Modules\Order\DTOs\WarehouseShipmentRequestDTO;
use App\Modules\Order\DTOs\WarehouseShipmentResponseDTO;
use App\Modules\Order\Exceptions\WarehouseApiException;
use Illuminate\Support\Facades\Http;

class WarehouseApiClient implements WarehouseApiClientInterface
{
    public function __construct(
        private readonly WarehouseConfigResolver $configResolver,
    ) {}

    public function createShipment(WarehouseShipmentRequestDTO $request): WarehouseShipmentResponseDTO
    {
        $config = $this->configResolver->resolve();
        $path = (string) ($config['create_shipment_path'] ?? '/v1/shipments');
        $url = rtrim((string) ($config['base_url'] ?? ''), '/').$path;

        $response = Http::timeout((int) ($config['timeout_seconds'] ?? 30))
            ->acceptJson()
            ->asJson()
            ->withToken((string) ($config['api_key'] ?? ''))
            ->post($url, $request->toPayload());

        if ($response->failed()) {
            throw WarehouseApiException::transportFailure(
                message: $response->json('message') ?? $response->body(),
                context: ['status' => $response->status()],
            );
        }

        return WarehouseShipmentResponseDTO::fromArray($response->json() ?? []);
    }
}
