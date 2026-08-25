<?php

namespace App\Modules\Order\DTOs;

use App\Models\Fulfillment;
use App\Models\Order;

readonly class WarehouseShipmentRequestDTO
{
    public function __construct(
        public string $orderUuid,
        public string $orderNumber,
        public array $shippingAddress,
        public array $lineItems,
        public string $customerEmail,
        public ?string $fulfillmentUuid = null,
    ) {}

    public static function fromOrder(Order $order, Fulfillment $fulfillment): self
    {
        $order->loadMissing('items');

        $lineItems = $order->items->map(fn ($item): array => [
            'sku' => $item->sku,
            'name' => $item->name,
            'quantity' => $item->quantity,
        ])->all();

        return new self(
            orderUuid: $order->uuid,
            orderNumber: $order->order_number,
            shippingAddress: $order->shipping_address ?? [],
            lineItems: $lineItems,
            customerEmail: (string) $order->customer_email,
            fulfillmentUuid: $fulfillment->uuid,
        );
    }

    public function toPayload(): array
    {
        return [
            'order_uuid' => $this->orderUuid,
            'order_number' => $this->orderNumber,
            'fulfillment_uuid' => $this->fulfillmentUuid,
            'customer_email' => $this->customerEmail,
            'shipping_address' => $this->shippingAddress,
            'line_items' => $this->lineItems,
        ];
    }
}
