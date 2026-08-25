<?php

namespace App\Modules\Order\Contracts;

use App\Modules\Order\DTOs\CheckoutPaymentResultDTO;
use App\Modules\Order\DTOs\PaymentIntentDTO;
use App\Modules\Payment\DTOs\PaymentRequest;
use App\Modules\Payment\DTOs\PaymentResponse;

interface CheckoutPaymentServiceInterface
{
    public function createPaymentIntent(
        string $amount,
        string $currency,
        array $metadata = [],
        ?string $idempotencyKey = null,
    ): PaymentIntentDTO;

    public function authorize(PaymentRequest $request): PaymentResponse;

    public function capture(
        string $transactionId,
        string $amount,
        string $currency,
        ?string $provider = null,
        ?string $idempotencyKey = null,
    ): PaymentResponse;

    public function processAuthorizeAndCapture(PaymentRequest $request): CheckoutPaymentResultDTO;
}
