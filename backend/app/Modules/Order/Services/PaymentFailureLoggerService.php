<?php

namespace App\Modules\Order\Services;

use App\Models\CheckoutPaymentFailure;

class PaymentFailureLoggerService
{
    public function log(
        string $idempotencyKey,
        ?string $customerEmail,
        ?float $amount,
        ?string $currency,
        string $failureCode,
        string $failureMessage,
        ?string $paymentIntentId = null,
        ?array $requestPayload = null,
        ?array $gatewayResponse = null,
        ?string $ipAddress = null,
        string $provider = 'stripe',
    ): CheckoutPaymentFailure {
        return CheckoutPaymentFailure::query()->create([
            'idempotency_key' => $idempotencyKey,
            'customer_email' => $customerEmail,
            'amount' => $amount,
            'currency' => $currency,
            'provider' => $provider,
            'failure_code' => $failureCode,
            'failure_message' => $failureMessage,
            'payment_intent_id' => $paymentIntentId,
            'request_payload' => $requestPayload,
            'gateway_response' => $gatewayResponse,
            'ip_address' => $ipAddress,
        ]);
    }
}
