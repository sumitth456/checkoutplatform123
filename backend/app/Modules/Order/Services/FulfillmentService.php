<?php

namespace App\Modules\Order\Services;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Modules\Order\Contracts\FulfillmentServiceInterface;

class FulfillmentService implements FulfillmentServiceInterface
{
    public function __construct(
        private readonly DelayFulfillmentEngineService $delayEngine,
    ) {}

    public function scheduleAfterPayment(Order $order): Fulfillment
    {
        return $this->delayEngine->createDelayedFulfillmentRecord($order);
    }
}
