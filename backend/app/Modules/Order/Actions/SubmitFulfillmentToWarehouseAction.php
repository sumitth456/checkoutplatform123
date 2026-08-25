<?php

namespace App\Modules\Order\Actions;

use App\Models\Fulfillment;
use App\Modules\Order\Services\DelayFulfillmentEngineService;

class SubmitFulfillmentToWarehouseAction
{
    public function __construct(
        private readonly DelayFulfillmentEngineService $engine,
    ) {}

    public function execute(Fulfillment $fulfillment): Fulfillment
    {
        return $this->engine->submitToWarehouse($fulfillment);
    }
}
