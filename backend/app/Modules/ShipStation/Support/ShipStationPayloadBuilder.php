<?php

namespace App\Modules\ShipStation\Support;

use App\Models\Order;

class ShipStationPayloadBuilder
{
    public function __construct(
        private readonly ShipStationFaceShieldLineItemClassifier $faceShieldLineItems,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildOrderPayload(Order $order): array
    {
        $order->loadMissing(['items', 'customer']);

        $prefix = (string) config('shipstation.order_key_prefix', 'cp-order-');
        $billTo = $this->mapAddress($order->billing_address ?? [], $order);
        $shipTo = $this->mapAddress($order->shipping_address ?? $order->billing_address ?? [], $order);

        return [
            'orderNumber' => (string) $order->order_number,
            'orderKey' => $prefix.$order->id,
            'orderDate' => ($order->placed_at ?? $order->created_at)?->toIso8601String(),
            'orderStatus' => 'awaiting_shipment',
            'customerUsername' => (string) ($order->customer_email ?? $order->customer?->email ?? 'customer'),
            'customerEmail' => (string) ($order->customer_email ?? $order->customer?->email ?? ''),
            'billTo' => $billTo,
            'shipTo' => $shipTo,
            'items' => $this->faceShieldLineItems->shippableLineItems($order)->map(fn ($item): array => [
                'sku' => (string) ($item->sku ?: 'SKU-'.$item->id),
                'name' => (string) $item->name,
                'quantity' => (int) $item->quantity,
                'unitPrice' => (float) $item->unit_price,
            ])->values()->all(),
            // CRM order totals unchanged; legacy API payload omits face-shield lines only.
            'amountPaid' => (float) $order->grand_total,
            'taxAmount' => (float) $order->tax_total,
            'shippingAmount' => (float) $order->shipping_total,
            'discountAmount' => (float) $order->discount_total,
            'paymentDate' => $order->paid_at?->toIso8601String(),
            'customerNotes' => null,
            'internalNotes' => 'Checkout Platform order #'.$order->id,
            'advancedOptions' => [
                'storeId' => null,
                'customField1' => (string) $order->uuid,
                'customField2' => (string) $order->id,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $address
     * @return array<string, mixed>
     */
    private function mapAddress(array $address, Order $order): array
    {
        $customer = $order->customer;

        return [
            'name' => trim((string) (
                $order->checkoutCustomerFullName()
                ?: ($address['name'] ?? null)
                ?: trim(($address['first_name'] ?? '').' '.($address['last_name'] ?? ''))
                ?: ($customer?->full_name ?? 'Customer')
            )),
            'company' => (string) ($address['company'] ?? $customer?->company ?? null),
            'street1' => (string) ($address['line1'] ?? $address['address_line1'] ?? ''),
            'street2' => (string) ($address['line2'] ?? $address['address_line2'] ?? ''),
            'city' => (string) ($address['city'] ?? ''),
            'state' => (string) ($address['state'] ?? $address['region'] ?? ''),
            'postalCode' => (string) ($address['postal_code'] ?? $address['zip'] ?? ''),
            'country' => strtoupper((string) ($address['country'] ?? $order->country_code ?? 'US')),
            'phone' => (string) ($order->customer_phone ?? $customer?->phone ?? ''),
        ];
    }
}
