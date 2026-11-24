<?php

namespace App\Modules\ShipStation\Support;

use App\Models\Order;
use App\Modules\ShipStation\Services\ShipStationConfigResolver;

class ShipStationV2PayloadBuilder
{
    public function __construct(
        private readonly ShipStationConfigResolver $config,
        private readonly ShipStationUsStateProvinceNormalizer $usStateProvinceNormalizer,
        private readonly ShipStationFaceShieldLineItemClassifier $faceShieldLineItems,
        private readonly ShipStationV2PackageWeightCalculator $packageWeight,
    ) {}

    /**
     * @return array{shipments: list<array<string, mixed>>}
     */
    public function buildReshipmentCreateShipmentsPayload(Order $order, \App\Models\Fulfillment $fulfillment, string $externalReference): array
    {
        $fulfillment->loadMissing(['items.orderItem.product', 'items.variant']);

        $shippableItems = collect();

        foreach ($fulfillment->items as $fulfillmentItem) {
            $orderItem = $fulfillmentItem->orderItem;

            if ($orderItem !== null) {
                $shippableItems->push($orderItem);
            }
        }

        $weightOz = max(1, $this->packageWeight->totalOuncesForShippableItems($order, $shippableItems));

        $entry = [
            'external_shipment_id' => $externalReference,
            'shipment_number' => $externalReference,
            'packages' => [
                [
                    'weight' => [
                        'value' => $weightOz,
                        'unit' => 'ounce',
                    ],
                ],
            ],
        ];

        $storeId = $this->config->storeId();

        if (filled($storeId)) {
            $entry['store_id'] = $storeId;
        }

        $warehouseId = $this->config->warehouseIdForStore($storeId);

        if (filled($warehouseId)) {
            $entry['warehouse_id'] = $warehouseId;
        }

        if ($this->config->isRealMode()) {
            $entry['create_sales_order'] = true;
            $entry['requested_shipment_service'] = (string) ($order->metadata['shipstation_service_code'] ?? 'usps_ground_advantage');
            $entry['ship_to'] = $this->buildAddress($order);
            $entry['items'] = $this->buildItemsFromFulfillment($fulfillment);

            $entry['advanced_options'] = array_filter([
                'custom_field1' => $this->crmAdminOrderUrl($order),
                'custom_field2' => (string) $order->id,
                'custom_field3' => (string) $externalReference,
            ]);
        }

        return ['shipments' => [$entry]];
    }

    /**
     * @return array{shipments: list<array<string, mixed>>}
     */
    public function buildCreateShipmentsPayload(Order $order): array
    {
        return [
            'shipments' => [
                $this->buildShipmentEntry($order),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildShipmentEntry(Order $order): array
    {
        $order->loadMissing(['items', 'customer']);

        $crmReference = ShipStationV2OrderReference::crmExternalReference($order);
        $shippableItems = $this->faceShieldLineItems->shippableLineItems($order);
        $weightOz = $this->packageWeight->totalOuncesForShippableItems($order, $shippableItems);

        $entry = [
            'external_shipment_id' => $crmReference,
            // Omit external_order_id when it would match external_shipment_id — ShipStation
            // create_sales_order otherwise maps both onto line-item options as duplicate ExternalOrderId.
            'shipment_number' => $crmReference,
            'packages' => [
                [
                    'weight' => [
                        'value' => $weightOz,
                        'unit' => 'ounce',
                    ],
                ],
            ],
        ];

        $storeId = $this->config->storeId();

        if (filled($storeId)) {
            $entry['store_id'] = $storeId;
        }

        $warehouseId = $this->config->warehouseIdForStore($storeId);

        if (filled($warehouseId)) {
            $entry['warehouse_id'] = $warehouseId;
        }

        if ($this->config->isRealMode()) {
            $entry['create_sales_order'] = true;
            $entry['requested_shipment_service'] = (string) ($order->metadata['shipstation_service_code'] ?? 'usps_ground_advantage');
            $entry['ship_to'] = $this->buildAddress($order);
            $entry['items'] = $this->buildItems($order);

            $metadataFrom = data_get($order->metadata, 'shipstation_ship_from');

            if (is_array($metadataFrom) && $metadataFrom !== []) {
                $entry['ship_from'] = $metadataFrom;
            }

            $crmAdminUrl = $this->crmAdminOrderUrl($order);

            if ($crmAdminUrl !== null) {
                $entry['advanced_options'] = [
                    'custom_field1' => $crmAdminUrl,
                ];
            }
        }

        return $entry;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCreateLabelPayload(): array
    {
        return [
            'label_format' => 'pdf',
            'label_layout' => '4x6',
            'label_download_type' => 'url',
        ];
    }

    private function crmAdminOrderUrl(Order $order): ?string
    {
        $uuid = (string) ($order->uuid ?? '');

        if ($uuid === '') {
            return null;
        }

        $baseUrl = rtrim((string) config('app.url', ''), '/');

        if ($baseUrl === '') {
            return null;
        }

        return $baseUrl.'/admin/orders/'.$uuid;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAddress(Order $order): array
    {
        $address = $order->shipping_address ?? $order->billing_address ?? [];
        $name = trim((string) (($address['first_name'] ?? '').' '.($address['last_name'] ?? '')));

        if ($name === '') {
            $name = $order->checkoutCustomerFullName() ?: 'Customer';
        }

        $countryCode = strtoupper((string) ($address['country'] ?? $order->country_code ?? 'US'));
        $rawState = (string) ($address['state'] ?? $address['region'] ?? '');
        $stateProvince = $this->usStateProvinceNormalizer->normalizeForCountry(
            $countryCode,
            $rawState !== '' ? $rawState : null,
        );

        return array_filter([
            'name' => $name,
            'phone' => (string) ($order->customer_phone ?? $address['phone'] ?? ''),
            'email' => (string) ($order->customer_email ?? ''),
            'address_line1' => (string) ($address['line1'] ?? $address['address_line1'] ?? ''),
            'address_line2' => (string) ($address['line2'] ?? $address['address_line2'] ?? ''),
            'city_locality' => (string) ($address['city'] ?? ''),
            'state_province' => $stateProvince,
            'postal_code' => (string) ($address['postal_code'] ?? $address['zip'] ?? ''),
            'country_code' => $countryCode === 'USA' ? 'US' : $countryCode,
            'address_residential_indicator' => 'yes',
        ], fn (mixed $value): bool => filled($value));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildItems(Order $order): array
    {
        return $this->faceShieldLineItems->shippableLineItems($order)
            ->map(function ($item): array {
                $name = filled($item->product?->name)
                    ? (string) $item->product->name
                    : (string) $item->name;

                $entry = [
                    'name' => $name,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                ];

                if (filled($item->sku)) {
                    $entry['sku'] = (string) $item->sku;
                }

                return $entry;
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildItemsFromFulfillment(\App\Models\Fulfillment $fulfillment): array
    {
        return $fulfillment->items->map(function ($item): array {
            $orderItem = $item->orderItem;
            $name = filled($orderItem?->product?->name)
                ? (string) $orderItem->product->name
                : (string) $item->name;

            $entry = [
                'name' => $name,
                'quantity' => (int) $item->quantity,
                'unit_price' => 0,
            ];

            if (filled($item->sku)) {
                $entry['sku'] = (string) $item->sku;
            }

            return $entry;
        })->values()->all();
    }
}
