<?php

namespace App\Modules\Order\Exceptions;

use App\Models\CheckoutPaymentFailure;
use App\Modules\Payment\Exceptions\PaymentGatewayException;
use RuntimeException;

class CheckoutPaymentFailedException extends RuntimeException
{
    public function __construct(
        public readonly CheckoutPaymentFailure $failure,
        PaymentGatewayException $previous,
    ) {
        parent::__construct($previous->getMessage(), 0, $previous);
    }
}
