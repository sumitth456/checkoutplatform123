<?php

namespace App\Modules\Payment\Gateways;

use App\Modules\Payment\DTOs\PaymentRequest;
use App\Modules\Payment\DTOs\PaymentResponse;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Enums\PaymentStatus;

class NMIGateway extends AbstractPaymentGateway
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::Nmi;
    }

    protected function providerEndpoint(string $action): string
    {
        return '';
    }

    protected function buildAuthorizePayload(PaymentRequest $request): array
    {
        return [
            'type' => 'auth',
            'amount' => $request->amount,
            'currency' => $request->currency,
            'payment_token' => $request->paymentToken,
            'security_key' => $this->config['security_key'] ?? '',
        ];
    }

    protected function normalizeAuthorizeResponse(array $raw, PaymentRequest $request): PaymentResponse
    {
        $transactionId = (string) ($raw['transactionid'] ?? $this->makeTransactionId('nmi'));

        return $this->successResponse(
            status: PaymentStatus::Authorized,
            transactionId: $transactionId,
            gatewayReference: (string) ($raw['authcode'] ?? $transactionId),
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
            gatewayReference: (string) ($raw['transactionid'] ?? $transactionId),
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
            gatewayReference: (string) ($raw['transactionid'] ?? $transactionId),
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
            gatewayReference: (string) ($raw['transactionid'] ?? $transactionId),
            amount: '0.00',
            currency: 'USD',
            raw: $raw,
        );
    }

    protected function normalizeStatusResponse(array $raw, string $transactionId): PaymentStatus
    {
        return match ($raw['response'] ?? null) {
            '1' => PaymentStatus::Captured,
            '2' => PaymentStatus::Declined,
            '3' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };
    }

    protected function httpClient(): \Illuminate\Http\Client\PendingRequest
    {
        return parent::httpClient()->asForm();
    }
}
