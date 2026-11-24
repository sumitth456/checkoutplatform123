<?php

namespace App\Modules\ShipStation\Services;

use App\Models\Order;

final class ShipStationWebhookOrderResolver
{
    public function resolveOrderIdFromPayload(array $payload): ?int
    {
        $customField2 = $payload['customField2'] ?? $payload['custom_field2'] ?? null;

        if (is_numeric($customField2)) {
            return (int) $customField2;
        }

        $orderKey = (string) ($payload['orderKey'] ?? $payload['order_key'] ?? '');
        $prefix = (string) config('shipstation.order_key_prefix', 'cp-order-');

        if (str_starts_with($orderKey, $prefix)) {
            $id = substr($orderKey, strlen($prefix));

            return is_numeric($id) ? (int) $id : null;
        }

        $shipStationOrderId = (int) ($payload['orderId'] ?? $payload['OrderID'] ?? 0);
        if ($shipStationOrderId > 0) {
            $order = Order::query()->where('shipstation_order_id', $shipStationOrderId)->first();

            return $order?->id;
        }

        $shipmentId = $this->extractShipmentIdFromPayload($payload);
        if ($shipmentId !== null) {
            $order = Order::query()
                ->whereHas('shipStationIntegration', fn ($q) => $q->where('external_shipment_id', $shipmentId))
                ->first();

            return $order?->id;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function extractShipmentIdFromPayload(array $payload): ?string
    {
        $resourceUrl = (string) ($payload['resource_url'] ?? $payload['ResourceUrl'] ?? '');
        $allowed = config('shipstation.tracking_sync.allowed_resource_url_hosts', []);

        if ($resourceUrl !== '') {
            $host = parse_url($resourceUrl, PHP_URL_HOST);
            if (! is_string($host) || ! in_array($host, $allowed, true)) {
                return null;
            }

            if (preg_match('#/shipments/(se-[A-Za-z0-9-]+)#i', $resourceUrl, $matches)) {
                return $matches[1];
            }
        }

        foreach (['shipment_id', 'ShipmentId'] as $key) {
            if (filled($payload[$key] ?? null)) {
                return (string) $payload[$key];
            }
        }

        return null;
    }
}