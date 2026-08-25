<?php

namespace App\Modules\Order\Actions;

use App\Models\CheckoutPaymentFailure;
use App\Modules\Order\DTOs\CreateFunnelOrderDTO;
use App\Modules\Order\Services\PaymentFailureLoggerService;

class LogCheckoutPaymentFailureAction
{
    public function __construct(
        private readonly PaymentFailureLoggerService $logger,
    ) {}

    public function execute(
        CreateFunnelOrderDTO $dto,
        string $idempotencyKey,
        ?string $paymentIntentId,
        string $failureCode,
        string $failureMessage,
        ?array $gatewayResponse = null,
        ?string $ipAddress = null,
        ?float $amount = null,
        ?string $currency = null,
    ): CheckoutPaymentFailure {
        return $this->logger->log(
            idempotencyKey: $idempotencyKey,
            customerEmail: $dto->customer->email,
            amount: $amount,
            currency: $currency,
            failureCode: $failureCode,
            failureMessage: $failureMessage,
            paymentIntentId: $paymentIntentId,
            requestPayload: [
                'customer_email' => $dto->customer->email,
                'line_items_count' => count($dto->lineItems),
                'metadata' => $dto->metadata,
            ],
            gatewayResponse: $gatewayResponse,
            ipAddress: $ipAddress ?? $dto->context->ipAddress,
        );
    }
}
