<?php

namespace App\Modules\ShipStation\Support;

use App\Models\ShipStationIntegrationLog;
use Illuminate\Support\Facades\Log;

final class ShipStationApiLogger
{
    /**
     * @param  array<string, mixed>|null  $request
     * @param  array<string, mixed>|null  $response
     * @param  array<string, mixed>  $context
     */
    public function log(
        string $method,
        string $path,
        ?array $request,
        ?array $response,
        int $httpStatus,
        bool $success,
        ?string $errorMessage = null,
        ?int $orderId = null,
        ?int $fulfillmentId = null,
        ?int $shipStationOrderId = null,
        ?int $shipStationShipmentId = null,
        array $context = [],
    ): void {
        $sanitizedRequest = ShipStationPayloadSanitizer::sanitize($request);
        $sanitizedResponse = ShipStationPayloadSanitizer::sanitize($response);

        Log::info('ShipStation API request', [
            'mode' => config('shipstation.mode'),
            'method' => strtoupper($method),
            'path' => $path,
            'payload' => $sanitizedRequest,
            'http_status' => $httpStatus,
            'success' => $success,
            'response' => $sanitizedResponse,
            'error' => $errorMessage,
        ]);

        ShipStationIntegrationLog::query()->create([
            'request_type' => strtolower($method).':'.trim($path, '/'),
            'order_id' => $orderId,
            'fulfillment_id' => $fulfillmentId,
            'shipstation_order_id' => $shipStationOrderId,
            'shipstation_shipment_id' => $shipStationShipmentId,
            'success' => $success,
            'http_status' => $httpStatus,
            'error_message' => $errorMessage,
            'request_payload' => $sanitizedRequest,
            'response_payload' => $sanitizedResponse,
            'context' => $context === [] ? null : $context,
        ]);
    }
}
