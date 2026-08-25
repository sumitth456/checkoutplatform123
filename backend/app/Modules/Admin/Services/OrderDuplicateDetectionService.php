<?php

namespace App\Modules\Admin\Services;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OrderDuplicateDetectionService
{
    private const DUPLICATE_WINDOW_HOURS = 24;

    public function countPotentialDuplicates(Order $order): int
    {
        return $this->findPotentialDuplicates($order)->count();
    }

    /**
     * @return Collection<int, Order>
     */
    public function findPotentialDuplicates(Order $order): Collection
    {
        if ($order->placed_at === null) {
            return collect();
        }

        if ($order->customer_id === null && blank($order->customer_email)) {
            return collect();
        }

        $windowStart = $order->placed_at->copy()->subHours(self::DUPLICATE_WINDOW_HOURS);
        $windowEnd = $order->placed_at->copy()->addHours(self::DUPLICATE_WINDOW_HOURS);

        return Order::query()
            ->whereKeyNot($order->getKey())
            ->whereBetween('placed_at', [$windowStart, $windowEnd])
            ->where('grand_total', $order->grand_total)
            ->when(
                filled($order->ip_address),
                fn (Builder $query) => $query->where('ip_address', $order->ip_address),
            )
            ->where(function (Builder $query) use ($order): void {
                if ($order->customer_id !== null) {
                    $query->where('customer_id', $order->customer_id);
                }

                if (filled($order->customer_email)) {
                    if ($order->customer_id !== null) {
                        $query->orWhere('customer_email', $order->customer_email);
                    } else {
                        $query->where('customer_email', $order->customer_email);
                    }
                }
            })
            ->orderBy('placed_at')
            ->get();
    }
}
