<?php

namespace App\Modules\Order\Actions;

use App\Models\Customer;
use App\Models\Order;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Order\Contracts\CustomerRepositoryInterface;
use App\Modules\Order\Contracts\OrderRepositoryInterface;
use App\Modules\Order\DTOs\CreateFunnelOrderDTO;
use App\Modules\Order\DTOs\OrderAttributionDTO;
use App\Modules\Order\DTOs\OrderTotalsDTO;
use App\Modules\Order\DTOs\ResolvedLineItemDTO;
use App\Modules\Order\Enums\OrderFulfillmentStatus;
use App\Modules\Order\Enums\OrderPaymentStatus;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Events\OrderPlaced;

class CreateFunnelOrderAction
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly CustomerRepositoryInterface $customers,
        private readonly InventoryService $inventory,
        private readonly GenerateOrderNumberAction $generateOrderNumber,
    ) {}

    /**
     * @param  list<ResolvedLineItemDTO>  $lineItems
     */
    public function execute(
        CreateFunnelOrderDTO $dto,
        Customer $customer,
        array $lineItems,
        OrderTotalsDTO $totals,
        OrderAttributionDTO $attribution,
    ): Order {
        $orderItems = collect($lineItems)
            ->map(fn (ResolvedLineItemDTO $item): array => $item->toOrderItemAttributes())
            ->all();

        $order = $this->orders->create(
            attributes: array_merge(
                [
                    'order_number' => $this->generateOrderNumber->execute(),
                    'customer_id' => $customer->id,
                    'status' => OrderStatus::Pending,
                    'payment_status' => OrderPaymentStatus::Pending,
                    'fulfillment_status' => OrderFulfillmentStatus::Unfulfilled,
                    'customer_email' => $customer->email,
                    'customer_phone' => $customer->phone,
                    'billing_address' => $dto->billingAddress?->toArray(),
                    'shipping_address' => $dto->shippingAddress?->toArray(),
                    'notes' => $dto->notes,
                    'metadata' => $dto->metadata,
                    'placed_at' => now(),
                ],
                $totals->toOrderAttributes(),
                $attribution->toOrderAttributes(),
                $dto->context->toOrderAttributes(),
            ),
            items: $orderItems,
        );

        foreach ($lineItems as $lineItem) {
            if (! $lineItem->variant->track_inventory) {
                continue;
            }

            $this->inventory->reserve(
                variant: $lineItem->variant,
                quantity: $lineItem->quantity,
                referenceType: Order::class,
                referenceId: $order->uuid,
                expiresAt: now()->addHours(24),
                metadata: ['order_uuid' => $order->uuid, 'sku' => $lineItem->variant->sku],
            );
        }

        $this->customers->recordOrderPlaced($customer, $totals->grandTotal);

        event(new OrderPlaced($order));

        return $order->fresh(['items.product', 'items.variant', 'customer']);
    }
}
