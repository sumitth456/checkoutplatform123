<?php

namespace App\Modules\Order\Actions;

use App\Models\Order;
use App\Models\Transaction;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Order\Contracts\CustomerRepositoryInterface;
use App\Modules\Order\Contracts\OrderRepositoryInterface;
use App\Modules\Order\DTOs\CheckoutPaymentInputDTO;
use App\Modules\Order\DTOs\CheckoutPaymentResultDTO;
use App\Modules\Order\DTOs\CreateFunnelOrderDTO;
use App\Modules\Order\DTOs\PreparedCheckoutDTO;
use App\Modules\Order\Enums\OrderFulfillmentStatus;
use App\Modules\Order\Enums\OrderPaymentStatus;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Events\OrderPlaced;

class CompletePaidCheckoutAction
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly CustomerRepositoryInterface $customers,
        private readonly InventoryService $inventory,
        private readonly GenerateOrderNumberAction $generateOrderNumber,
    ) {}

    public function execute(
        CreateFunnelOrderDTO $dto,
        PreparedCheckoutDTO $prepared,
        CheckoutPaymentResultDTO $payment,
        ?CheckoutPaymentInputDTO $paymentInput = null,
    ): Order {
        $checkoutDto = $prepared->checkoutDto ?? $dto;
        $isAch = $paymentInput?->isAch() ?? (($payment->captureResponse->metadata['payment_method'] ?? null) === 'ach');
        $captureMeta = $payment->captureResponse->metadata ?? [];

        $orderItems = collect($prepared->lineItems)
            ->map(fn ($item) => $item->toOrderItemAttributes())
            ->all();

        $order = $this->orders->create(
            attributes: array_merge(
                [
                    'order_number' => $this->generateOrderNumber->execute(),
                    'customer_id' => $prepared->customer->id,
                    'status' => OrderStatus::Completed,
                    'payment_status' => OrderPaymentStatus::Paid,
                    'payment_method_type' => $isAch ? 'ach' : 'card',
                    'fulfillment_status' => OrderFulfillmentStatus::Unfulfilled,
                    'customer_email' => $prepared->customer->email,
                    'customer_phone' => $prepared->customer->phone,
                    'billing_address' => $checkoutDto->billingAddress?->toArray(),
                    'shipping_address' => $checkoutDto->shippingAddress?->toArray(),
                    'notes' => $checkoutDto->notes,
                    'coupon_code' => $checkoutDto->couponCode,
                    'coupon_id' => $checkoutDto->couponId,
                    'metadata' => array_merge($checkoutDto->metadata, [
                        'payment_intent_id' => $payment->paymentIntent->id,
                        'payment_token' => $paymentInput?->paymentToken,
                        'payment_method' => $isAch ? 'ach' : 'card',
                        'authorization_id' => $captureMeta['authorization_id'] ?? null,
                        'account_type' => $captureMeta['account_type'] ?? null,
                        'last4' => $captureMeta['last4'] ?? null,
                        'ach_provider_id' => $captureMeta['ach_provider_id'] ?? null,
                    ]),
                    'placed_at' => now(),
                    'paid_at' => now(),
                ],
                $prepared->totals->toOrderAttributes(),
                $prepared->attribution->toOrderAttributes(),
                $dto->context->toOrderAttributes(),
            ),
            items: $orderItems,
        );

        foreach ($prepared->lineItems as $lineItem) {
            if (! $lineItem->variant->track_inventory) {
                continue;
            }

            $reservation = $this->inventory->reserve(
                variant: $lineItem->variant,
                quantity: $lineItem->quantity,
                referenceType: Order::class,
                referenceId: $order->uuid,
                expiresAt: now()->addMinutes(5),
                metadata: ['order_uuid' => $order->uuid, 'sku' => $lineItem->variant->sku],
            );

            $this->inventory->commitReservation($reservation->uuid);
        }

        Transaction::query()->create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'type' => 'capture',
            'status' => $payment->captureResponse->status->value,
            'external_id' => $payment->captureResponse->transactionId,
            'external_status' => $payment->captureResponse->status->value,
            'authorization_id' => $captureMeta['authorization_id'] ?? null,
            'currency' => $order->currency,
            'amount' => $order->grand_total,
            'payment_method_type' => $isAch ? 'ach' : 'card',
            'payment_method_last4' => $captureMeta['last4'] ?? null,
            'payment_method_brand' => $isAch
                ? ucfirst((string) ($captureMeta['account_type'] ?? 'Checking'))
                : null,
            'processed_at' => now(),
            'gateway_response' => $payment->toArray(),
            'metadata' => [
                'payment_method' => $isAch ? 'ach' : 'card',
                'provider' => $payment->captureResponse->provider->value,
            ],
        ]);

        $this->customers->recordOrderPlaced($prepared->customer, $prepared->totals->grandTotal);

        event(new OrderPlaced($order));

        return $order->fresh(['items.product', 'items.variant', 'customer', 'transactions']);
    }
}
