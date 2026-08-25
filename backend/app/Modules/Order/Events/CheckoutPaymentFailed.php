<?php

namespace App\Modules\Order\Events;

use App\Models\CheckoutPaymentFailure;
use App\Modules\Payment\Exceptions\PaymentGatewayException;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CheckoutPaymentFailed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly CheckoutPaymentFailure $failure,
        public readonly PaymentGatewayException $exception,
        public readonly string $idempotencyKey,
    ) {}
}
