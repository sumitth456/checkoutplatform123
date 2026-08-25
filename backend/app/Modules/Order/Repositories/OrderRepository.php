<?php

namespace App\Modules\Order\Repositories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Modules\Order\Contracts\OrderRepositoryInterface;
use App\Modules\Order\Enums\OrderPaymentStatus;
use App\Modules\Order\Enums\OrderStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderRepository implements OrderRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Order::query()
            ->with(['items', 'customer'])
            ->latest('placed_at');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (isset($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search): void {
                $builder->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function findByUuid(string $uuid, bool $withTrashed = false): ?Order
    {
        $query = Order::query()->with(['items.product', 'items.variant', 'customer', 'transactions']);

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->where('uuid', $uuid)->first();
    }

    public function create(array $attributes, array $items): Order
    {
        $order = Order::query()->create($attributes);

        foreach ($items as $item) {
            OrderItem::query()->create(array_merge($item, ['order_id' => $order->id]));
        }

        return $order->fresh(['items', 'customer']);
    }

    public function update(Order $order, array $attributes): Order
    {
        $order->update($attributes);

        return $order->fresh(['items', 'customer', 'transactions']);
    }
}
