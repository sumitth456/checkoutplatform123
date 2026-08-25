<?php

namespace App\Modules\Order\Services;

use App\Models\Customer;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\Variant;
use App\Modules\Inventory\Enums\ReservationStatus;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Order\Contracts\OrderRepositoryInterface;
use App\Modules\Order\Enums\OrderFulfillmentStatus;
use App\Modules\Order\Enums\OrderPaymentStatus;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Events\OrderCancelled;
use App\Modules\Order\Events\OrderPaid;
use App\Modules\Order\Events\OrderPlaced;
use App\Modules\Payment\DTOs\PaymentRequest;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Services\PaymentOrchestrator;
use App\Modules\Product\Enums\ProductType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly InventoryService $inventory,
        private readonly PaymentOrchestrator $payments,
    ) {}

    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->orders->paginate($filters, $perPage);
    }

    public function find(string $uuid): Order
    {
        $order = $this->orders->findByUuid($uuid);

        if ($order === null) {
            throw (new ModelNotFoundException)->setModel(Order::class);
        }

        return $order;
    }

    public function place(array $data): Order
    {
        return DB::transaction(function () use ($data): Order {
            $customer = $this->resolveCustomer($data);
            $lineItems = $this->buildLineItems($data['items']);
            $currency = strtoupper((string) ($data['currency'] ?? $lineItems['currency'] ?? 'USD'));

            $subtotal = collect($lineItems['items'])->sum('total');
            $discountTotal = (float) ($data['discount_total'] ?? 0);
            $taxTotal = (float) ($data['tax_total'] ?? 0);
            $shippingTotal = (float) ($data['shipping_total'] ?? 0);
            $grandTotal = max(0, $subtotal - $discountTotal + $taxTotal + $shippingTotal);

            $order = $this->orders->create([
                'order_number' => $this->generateOrderNumber(),
                'customer_id' => $customer->id,
                'status' => OrderStatus::Pending,
                'payment_status' => OrderPaymentStatus::Pending,
                'fulfillment_status' => OrderFulfillmentStatus::Unfulfilled,
                'currency' => $currency,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'shipping_total' => $shippingTotal,
                'grand_total' => $grandTotal,
                'customer_email' => $customer->email,
                'customer_phone' => $customer->phone,
                'billing_address' => $data['billing_address'] ?? null,
                'shipping_address' => $data['shipping_address'] ?? null,
                'notes' => $data['notes'] ?? null,
                'placed_at' => now(),
                'metadata' => $data['metadata'] ?? [],
            ], $lineItems['items']);

            $this->reserveInventoryForOrder($order, $lineItems['reservations']);

            Event::dispatch(new OrderPlaced($order));

            return $order->fresh(['items', 'customer']);
        });
    }

    public function checkout(Order $order, array $data): Order
    {
        if ($order->payment_status !== OrderPaymentStatus::Pending) {
            throw ValidationException::withMessages([
                'order' => ['Order payment has already been processed.'],
            ]);
        }

        if ($order->status === OrderStatus::Cancelled) {
            throw ValidationException::withMessages([
                'order' => ['Cancelled orders cannot be checked out.'],
            ]);
        }

        $response = $this->payments->processPayment(new PaymentRequest(
            amount: number_format((float) $order->grand_total, 2, '.', ''),
            currency: $order->currency,
            paymentToken: (string) $data['payment_token'],
            customerId: (string) $order->customer_id,
            orderId: $order->uuid,
            provider: isset($data['provider']) ? PaymentProvider::from($data['provider']) : null,
            idempotencyKey: (string) $data['idempotency_key'],
            metadata: ['order_uuid' => $order->uuid, 'order_number' => $order->order_number],
        ));

        $captureResponse = $this->payments->capture(
            transactionId: $response->transactionId,
            amount: number_format((float) $order->grand_total, 2, '.', ''),
            currency: $order->currency,
            provider: $response->provider->value,
            idempotencyKey: (string) $data['idempotency_key'].'-capture',
        );

        return DB::transaction(function () use ($order, $captureResponse, $response): Order {
            Transaction::query()->create([
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'type' => 'authorize',
                'status' => $response->status->value,
                'external_id' => $response->transactionId,
                'external_status' => $response->status->value,
                'currency' => $order->currency,
                'amount' => $order->grand_total,
                'processed_at' => now(),
                'gateway_response' => $response->toArray(),
            ]);

            Transaction::query()->create([
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'type' => 'capture',
                'status' => $captureResponse->status->value,
                'external_id' => $captureResponse->transactionId,
                'external_status' => $captureResponse->status->value,
                'currency' => $order->currency,
                'amount' => $order->grand_total,
                'processed_at' => now(),
                'gateway_response' => $captureResponse->toArray(),
            ]);

            $this->commitInventoryForOrder($order);

            $order = $this->orders->update($order, [
                'status' => OrderStatus::Completed,
                'payment_status' => OrderPaymentStatus::Paid,
                'paid_at' => now(),
            ]);

            Event::dispatch(new OrderPaid($order, $captureResponse));

            return $order;
        });
    }

    public function cancel(Order $order, ?string $reason = null): Order
    {
        if ($order->status === OrderStatus::Cancelled) {
            return $order;
        }

        if ($order->payment_status === OrderPaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'order' => ['Paid orders must be refunded instead of cancelled.'],
            ]);
        }

        $this->releaseInventoryForOrder($order);

        $order = $this->orders->update($order, [
            'status' => OrderStatus::Cancelled,
            'payment_status' => OrderPaymentStatus::Failed,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason ?? 'customer_cancelled',
        ]);

        Event::dispatch(new OrderCancelled($order, $reason));

        return $order;
    }

    private function resolveCustomer(array $data): Customer
    {
        if (isset($data['customer_id'])) {
            $customer = Customer::query()->find($data['customer_id']);

            if ($customer === null) {
                throw ValidationException::withMessages([
                    'customer_id' => ['Customer not found.'],
                ]);
            }

            return $customer;
        }

        $payload = $data['customer'] ?? null;

        if (! is_array($payload) || blank($payload['email'] ?? null)) {
            throw ValidationException::withMessages([
                'customer' => ['Customer email is required when customer_id is not provided.'],
            ]);
        }

        return Customer::query()->firstOrCreate(
            ['email' => $payload['email']],
            [
                'first_name' => $payload['first_name'] ?? null,
                'last_name' => $payload['last_name'] ?? null,
                'phone' => $payload['phone'] ?? null,
                'currency' => isset($data['currency']) ? strtoupper($data['currency']) : null,
            ],
        );
    }

    /**
     * @param  list<array{variant_uuid: string, quantity: int}>  $items
     * @return array{items: list<array<string, mixed>>, reservations: list<array{variant: Variant, quantity: int}>, currency: string}
     */
    private function buildLineItems(array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => ['At least one line item is required.'],
            ]);
        }

        $builtItems = [];
        $reservations = [];
        $currency = 'USD';

        foreach ($items as $index => $item) {
            $variant = Variant::query()
                ->with('product')
                ->where('uuid', $item['variant_uuid'])
                ->first();

            if ($variant === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.variant_uuid" => ['Variant not found.'],
                ]);
            }

            $quantity = (int) $item['quantity'];

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => ['Quantity must be at least 1.'],
                ]);
            }

            $unitPrice = (float) $variant->price;
            $lineTotal = round($unitPrice * $quantity, 2);
            $currency = strtoupper((string) $variant->currency);

            $builtItems[] = [
                'product_id' => $variant->product_id,
                'variant_id' => $variant->id,
                'name' => $variant->name ?? $variant->product?->name ?? 'Item',
                'sku' => $variant->sku,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total' => $lineTotal,
                'is_subscription' => $variant->product?->type === ProductType::Subscription,
            ];

            if ($variant->track_inventory) {
                $reservations[] = ['variant' => $variant, 'quantity' => $quantity];
            }
        }

        return [
            'items' => $builtItems,
            'reservations' => $reservations,
            'currency' => $currency,
        ];
    }

    /** @param list<array{variant: Variant, quantity: int}> $reservations */
    private function reserveInventoryForOrder(Order $order, array $reservations): void
    {
        foreach ($reservations as $reservation) {
            $this->inventory->reserve(
                variant: $reservation['variant'],
                quantity: $reservation['quantity'],
                referenceType: Order::class,
                referenceId: $order->uuid,
                expiresAt: now()->addHours(24),
                metadata: ['order_uuid' => $order->uuid],
            );
        }
    }

    private function commitInventoryForOrder(Order $order): void
    {
        $this->eachActiveReservation($order, function (InventoryReservation $reservation): void {
            $this->inventory->commitReservation($reservation->uuid);
        });
    }

    private function releaseInventoryForOrder(Order $order): void
    {
        $this->eachActiveReservation($order, function (InventoryReservation $reservation): void {
            $this->inventory->releaseReservation($reservation->uuid);
        });
    }

    private function eachActiveReservation(Order $order, callable $callback): void
    {
        InventoryReservation::query()
            ->where('reference_type', Order::class)
            ->where('reference_id', $order->uuid)
            ->where('status', ReservationStatus::Active)
            ->get()
            ->each($callback);
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }
}
