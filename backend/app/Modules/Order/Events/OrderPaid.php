<?php

namespace App\Modules\Order\Events;

use App\Models\Order;
use App\Modules\Payment\DTOs\PaymentResponse;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPaid
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly PaymentResponse $paymentResponse,
    ) {}
}
