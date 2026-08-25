<?php

namespace App\Modules\Order\Contracts;

use App\Models\Fulfillment;
use App\Models\Order;
use Illuminate\Support\Collection;

interface FulfillmentRepositoryInterface
{
    public function createForOrder(Order $order, int $delayDays, \DateTimeInterface $scheduledShipDate): Fulfillment;

    public function findDueForWarehouseSubmission(): Collection;

    public function findByUuid(string $uuid): ?Fulfillment;

    public function lockForUpdate(int $fulfillmentId): ?Fulfillment;
}
