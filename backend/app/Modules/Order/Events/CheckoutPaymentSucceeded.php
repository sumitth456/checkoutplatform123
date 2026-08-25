<?php

namespace App\Modules\Order\Events;

use App\Models\Order;
use App\Modules\Order\DTOs\CheckoutPaymentResultDTO;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CheckoutPaymentSucceeded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly CheckoutPaymentResultDTO $payment,
    ) {}
}
