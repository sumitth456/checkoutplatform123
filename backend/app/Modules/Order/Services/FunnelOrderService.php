<?php

namespace App\Modules\Order\Services;

use App\Models\Order;
use App\Modules\Order\Actions\CalculateOrderTotalsAction;
use App\Modules\Order\Actions\CreateFunnelOrderAction;
use App\Modules\Order\Actions\ResolveFunnelAction;
use App\Modules\Order\Actions\ResolveLineItemAction;
use App\Modules\Order\Actions\ResolveOrCreateCustomerAction;
use App\Modules\Order\Actions\VerifyInventoryAction;
use App\Modules\Order\DTOs\CreateFunnelOrderDTO;
use Illuminate\Support\Facades\DB;

class FunnelOrderService
{
    public function __construct(
        private readonly ResolveFunnelAction $resolveFunnel,
        private readonly ResolveLineItemAction $resolveLineItem,
        private readonly VerifyInventoryAction $verifyInventory,
        private readonly CalculateOrderTotalsAction $calculateTotals,
        private readonly ResolveOrCreateCustomerAction $resolveCustomer,
        private readonly CreateFunnelOrderAction $createOrder,
    ) {}

    public function place(CreateFunnelOrderDTO $dto): Order
    {
        return DB::transaction(function () use ($dto): Order {
            $attribution = $this->resolveFunnel->execute($dto);

            $resolvedItems = [];

            foreach ($dto->lineItems as $index => $lineItem) {
                $resolved = $this->resolveLineItem->execute($lineItem, $index);
                $this->verifyInventory->execute($resolved, $index);
                $resolvedItems[] = $resolved;
            }

            $customer = $this->resolveCustomer->execute($dto);
            $totals = $this->calculateTotals->execute($dto, $resolvedItems);

            return $this->createOrder->execute(
                dto: $dto,
                customer: $customer,
                lineItems: $resolvedItems,
                totals: $totals,
                attribution: $attribution,
            );
        });
    }
}
