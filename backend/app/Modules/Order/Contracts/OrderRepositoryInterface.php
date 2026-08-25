<?php

namespace App\Modules\Order\Contracts;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findByUuid(string $uuid, bool $withTrashed = false): ?Order;

    public function create(array $attributes, array $items): Order;

    public function update(Order $order, array $attributes): Order;
}
