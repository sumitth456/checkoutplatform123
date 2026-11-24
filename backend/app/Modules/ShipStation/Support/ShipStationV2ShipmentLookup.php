<?php

namespace App\Modules\ShipStation\Support;

use App\Models\Order;
use App\Modules\ShipStation\Exceptions\ShipStationApiException;
use App\Modules\ShipStation\Services\ShipStationV2ApiClient;

final class ShipStationV2ShipmentLookup
{
    public function __construct(
        private readonly ShipStationV2ApiClient $v2,
    ) {}

    /**
     * @return array<string, mixed>|null Normalized shipment body when found.
     */
    public function findByExternalShipmentId(string $externalShipmentId, ?int $orderId = null): ?array
    {
        if ($externalShipmentId === '') {
            return null;
        }

        try {
            $body = $this->v2->getShipmentByExternalShipmentId($externalShipmentId, $orderId);
        } catch (ShipStationApiException $exception) {
            if ($exception->httpStatus === 404) {
                return null;
            }

            throw $exception;
        }

        return $this->normalizeShipmentBody($body);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForOrder(Order $order): ?array
    {
        $order->loadMissing('shipStationIntegration');

        $candidates = array_values(array_unique(array_filter([
            ShipStationV2OrderReference::crmExternalReference($order),
            $order->shipStationIntegration?->external_shipment_id,
            ShipStationV2OrderReference::legacyExternalShipmentId($order),
        ], fn (?string $value): bool => filled($value))));

        foreach ($candidates as $externalId) {
            if (str_starts_with((string) $externalId, 'se-') && ! str_contains((string) $externalId, 'cp-')) {
                try {
                    $body = $this->v2->getShipment((string) $externalId, $order->id);

                    return $this->normalizeShipmentBody($body);
                } catch (ShipStationApiException $exception) {
                    if ($exception->httpStatus !== 404) {
                        throw $exception;
                    }
                }

                continue;
            }

            $found = $this->findByExternalShipmentId((string) $externalId, $order->id);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function normalizeShipmentBody(array $body): array
    {
        if (isset($body['shipments'][0]) && is_array($body['shipments'][0])) {
            return $body['shipments'][0];
        }

        return $body;
    }
}
