<?php

namespace App\Modules\Order\Contracts;

use App\Models\Fulfillment;
use App\Models\Order;

interface FulfillmentServiceInterface
{
    public function scheduleAfterPayment(Order $order): Fulfillment;
}
