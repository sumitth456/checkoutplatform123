<?php

namespace App\Modules\Order\DTOs;

use App\Modules\Payment\DTOs\PaymentResponse;

readonly class CheckoutPaymentResultDTO
{
    public function __construct(
        public PaymentIntentDTO $paymentIntent,
        public PaymentResponse $authorizeResponse,
        public PaymentResponse $captureResponse,
    ) {}

    public function toArray(): array
    {
        return [
            'payment_intent_id' => $this->paymentIntent->id,
            'client_secret' => $this->paymentIntent->clientSecret,
            'authorize' => $this->authorizeResponse->toArray(),
            'capture' => $this->captureResponse->toArray(),
        ];
    }
}
