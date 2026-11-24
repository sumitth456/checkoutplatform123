<?php

namespace App\Modules\ShipStation\Services;

use App\Modules\ShipStation\Contracts\ShipStationServiceInterface;
use App\Modules\ShipStation\Enums\ShipStationMode;
use App\Modules\ShipStation\Exceptions\ShipStationApiException;

class MockShipStationService implements ShipStationServiceInterface
{
    public function __construct(

        private readonly ShipStationV2ApiClient $v2,

    ) {}

    public function mode(): string
    {

        return ShipStationMode::Mock->value;

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

                'carrierCode' => (string) ($service['carrier_code'] ?? $carrierCode),

                'code' => (string) ($service['service_code'] ?? ''),

                'name' => (string) ($service['name'] ?? $service['service_code'] ?? 'Service'),

            ])

            ->values()

            ->all();

    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getStores(): array
    {

        return [

            [

                'storeId' => 1582173,

                'storeName' => 'Checkout Platform Mock Store',

                'marketplaceId' => 0,

                'marketplaceName' => 'Checkout Platform',

                'active' => true,

            ],

        ];

    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload): array
    {

        $this->v2->getCarriers();

        return [

            'orderId' => abs(crc32((string) ($payload['orderKey'] ?? $payload['orderNumber'] ?? 'order'))),

            'orderNumber' => (string) ($payload['orderNumber'] ?? ''),

            'orderKey' => (string) ($payload['orderKey'] ?? ''),

            'orderStatus' => 'awaiting_shipment',

            'customerEmail' => (string) ($payload['customerEmail'] ?? ''),

            'shipTo' => $payload['shipTo'] ?? [],

            'items' => $payload['items'] ?? [],

            'mock_mode' => true,

            'mock_api' => 'GET /v2/carriers',

        ];

    }

    /**
     * @return array<string, mixed>
     */
    public function getOrder(int $orderId): array
    {

        throw new ShipStationApiException('Use ShipStation mock fulfillment flow (v2 shipments) instead of v1 getOrder.');
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<array<string, mixed>>
     */
    public function listOrders(array $params = []): array
    {

        return [];

    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createShipment(array $payload): array
    {

        $response = $this->v2->createShipments([

            'shipments' => [

                [

                    'warehouse_id' => (string) config('shipstation.mock_warehouse_id', 'se-28529731'),

                    'packages' => [

                        [

                            'weight' => [

                                'value' => (int) ($payload['weight']['value'] ?? 16),

                                'unit' => 'ounce',

                            ],

                        ],

                    ],

                ],

            ],

        ]);

        $shipment = $response['shipments'][0] ?? [];

        return [

            'shipmentId' => $shipment['shipment_id'] ?? null,

            'orderId' => $payload['orderId'] ?? null,

            'carrierCode' => $shipment['service_code'] ?? null,

            'serviceCode' => $shipment['requested_shipment_service'] ?? $shipment['service_code'] ?? null,

            'trackingNumber' => null,

            'shipStatus' => $shipment['shipment_status'] ?? 'pending',

            'shipmentCost' => data_get($shipment, 'retail_rate.amount'),

            '_v2' => $shipment,

        ];

    }

    /**
     * @return array<string, mixed>
     */
    public function getShipment(int $shipmentId): array
    {

        return $this->v2->getShipment((string) $shipmentId);

    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<array<string, mixed>>
     */
    public function listShipments(array $params = []): array
    {

        if (isset($params['shipmentId'])) {

            return [$this->getShipment((int) $params['shipmentId'])];

        }

        return [];

    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public function getShippingRates(array $payload): array
    {

        throw new ShipStationApiException('Rate shopping is not supported against the official ShipStation mock API.');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createLabel(array $payload): array
    {

        $shipmentId = (string) ($payload['shipmentId'] ?? $payload['shipment_id'] ?? '');

        if ($shipmentId === '' && isset($payload['orderId'])) {

            $created = $this->createShipment($payload);

            $shipmentId = (string) ($created['shipmentId'] ?? '');

        }

        if ($shipmentId === '') {

            throw new ShipStationApiException('shipmentId is required to create a label via the ShipStation mock API.');
        }

        $response = $this->v2->createLabelFromShipment($shipmentId, [

            'label_format' => 'pdf',

            'label_layout' => '4x6',

            'label_download_type' => 'url',

        ]);

        return [

            'shipmentId' => $response['shipment_id'] ?? $shipmentId,

            'labelId' => $response['label_id'] ?? null,

            'trackingNumber' => $response['tracking_number'] ?? null,

            'carrierCode' => $response['carrier_code'] ?? $response['carrier_id'] ?? null,

            'serviceCode' => $response['service_code'] ?? null,

            'shipmentCost' => data_get($response, 'shipment_cost.amount'),

            'labelData' => null,

            'status' => $response['status'] ?? null,

            'labelDownload' => $response['label_download'] ?? null,

            '_v2' => $response,

        ];

    }

    /**
     * @return array<string, mixed>
     */
    public function getLabel(int $shipmentId): array
    {

        throw new ShipStationApiException('Use getLabelById via the ShipStation mock fulfillment service.');
    }

    public function voidLabel(int $shipmentId): array
    {

        throw new ShipStationApiException('Void label is not implemented for the ShipStation mock API.');
    }

    public function cancelShipment(int $shipmentId): array
    {

        throw new ShipStationApiException('Cancel shipment is not implemented for the ShipStation mock API.');
    }
}
