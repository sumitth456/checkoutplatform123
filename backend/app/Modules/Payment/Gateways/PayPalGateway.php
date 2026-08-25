<?php

namespace App\Modules\Payment\Gateways;

use App\Modules\Payment\DTOs\PaymentRequest;
use App\Modules\Payment\DTOs\PaymentResponse;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Enums\PaymentStatus;

class PayPalGateway extends AbstractPaymentGateway
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::PayPal;
    }

    protected function providerEndpoint(string $action): string
    {
        return match ($action) {
            'authorize' => '/v2/checkout/orders/authorize',
            'capture' => '/v2/checkout/orders/capture',
            'refund' => '/v2/payments/captures/refund',
            'void' => '/v2/checkout/orders/void',
            'status' => '/v2/checkout/orders/status',
        };
    }

    protected function buildAuthorizePayload(PaymentRequest $request): array
    {
        return [
            'intent' => 'AUTHORIZE',
            'payment_source' => ['token' => ['id' => $request->paymentToken]],
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => $request->currency,
                    'value' => $request->amount,
                ],
            ]],
        ];
    }

    protected function normalizeAuthorizeResponse(array $raw, PaymentRequest $request): PaymentResponse
    {
        return $this->successResponse(
            status: PaymentStatus::Authorized,
            transactionId: (string) ($raw['id'] ?? $this->makeTransactionId('pp')),
            gatewayReference: (string) ($raw['purchase_units'][0]['payments']['authorizations'][0]['id'] ?? $raw['id'] ?? ''),
            amount: $request->amount,
            currency: $request->currency,
            raw: $raw,
        );
    }

    protected function normalizeCaptureResponse(array $raw, string $transactionId, string $amount, string $currency): PaymentResponse
    {
        return $this->successResponse(
            status: PaymentStatus::Captured,
            transactionId: $transactionId,
            gatewayReference: (string) ($raw['id'] ?? $transactionId),
            amount: $amount,
            currency: $currency,
            raw: $raw,
        );
    }

    protected function normalizeRefundResponse(array $raw, string $transactionId, string $amount, string $currency): PaymentResponse
    {
        return $this->successResponse(
            status: PaymentStatus::Refunded,
            transactionId: $transactionId,
            gatewayReference: (string) ($raw['id'] ?? $transactionId),
            amount: $amount,
            currency: $currency,
            raw: $raw,
        );
    }

    protected function normalizeVoidResponse(array $raw, string $transactionId): PaymentResponse
    {
        return $this->successResponse(
            status: PaymentStatus::Voided,
            transactionId: $transactionId,
            gatewayReference: (string) ($raw['id'] ?? $transactionId),
            amount: '0.00',
            currency: 'USD',
            raw: $raw,
        );
    }

    protected function normalizeStatusResponse(array $raw, string $transactionId): PaymentStatus
    {
        return match ($raw['status'] ?? 'CREATED') {
            'APPROVED', 'AUTHORIZED' => PaymentStatus::Authorized,
            'COMPLETED' => PaymentStatus::Captured,
            'VOIDED' => PaymentStatus::Voided,
            default => PaymentStatus::Pending,
        };
    }
}
