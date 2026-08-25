<?php

namespace App\Modules\Order\Actions;

use App\Models\Order;
use App\Modules\Fulfillment\Services\FulfillmentOrchestratorService;

class CreateDelayedFulfillmentAction
{
    public function __construct(
        private readonly FulfillmentOrchestratorService $orchestrator,
    ) {}

    public function execute(Order $order): \App\Models\Fulfillment
    {
        return $this->orchestrator->createFromPaidOrder($order->fresh(['items.product', 'customer']));
    }
}
