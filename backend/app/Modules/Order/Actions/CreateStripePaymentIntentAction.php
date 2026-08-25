<?php

namespace App\Modules\Order\Actions;

use App\Modules\Order\Contracts\CheckoutPaymentServiceInterface;
use App\Modules\Order\DTOs\PaymentIntentDTO;

class CreateStripePaymentIntentAction
{
    public function __construct(
        private readonly CheckoutPaymentServiceInterface $checkoutPayment,
    ) {}

    public function execute(
        string $amount,
        string $currency,
        array $metadata = [],
        ?string $idempotencyKey = null,
    ): PaymentIntentDTO {
        return $this->checkoutPayment->createPaymentIntent(
            amount: $amount,
            currency: $currency,
            metadata: $metadata,
            idempotencyKey: $idempotencyKey,
        );
    }
}
