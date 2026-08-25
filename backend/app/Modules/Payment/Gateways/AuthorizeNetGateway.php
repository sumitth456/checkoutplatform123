<?php

namespace App\Modules\Payment\Gateways;

use App\Modules\Payment\DTOs\PaymentRequest;
use App\Modules\Payment\DTOs\PaymentResponse;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Enums\PaymentStatus;

class AuthorizeNetGateway extends AbstractPaymentGateway
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::AuthorizeNet;
    }

    protected function providerEndpoint(string $action): string
    {
        return '';
    }

    protected function buildAuthorizePayload(PaymentRequest $request): array
    {
        return [
            'createTransactionRequest' => [
                'transactionRequest' => [
                    'transactionType' => 'authOnlyTransaction',
                    'amount' => $request->amount,
                    'payment' => ['opaqueData' => ['dataValue' => $request->paymentToken]],
                ],
            ],
        ];
    }

    protected function normalizeAuthorizeResponse(array $raw, PaymentRequest $request): PaymentResponse
    {
        $transactionId = (string) data_get($raw, 'transactionResponse.transId', $this->makeTransactionId('anet'));

        return $this->successResponse(
            status: PaymentStatus::Authorized,
            transactionId: $transactionId,
            gatewayReference: (string) data_get($raw, 'transactionResponse.authCode', $transactionId),
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
            gatewayReference: (string) data_get($raw, 'transactionResponse.transId', $transactionId),
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
            gatewayReference: (string) data_get($raw, 'transactionResponse.transId', $transactionId),
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
            gatewayReference: (string) data_get($raw, 'transactionResponse.transId', $transactionId),
            amount: '0.00',
            currency: 'USD',
            raw: $raw,
        );
    }

    protected function normalizeStatusResponse(array $raw, string $transactionId): PaymentStatus
    {
        return match (data_get($raw, 'transaction.transactionStatus')) {
            'authorizedPendingCapture' => PaymentStatus::Authorized,
            'capturedPendingSettlement', 'settledSuccessfully' => PaymentStatus::Captured,
            'voided' => PaymentStatus::Voided,
            default => PaymentStatus::Pending,
        };
    }
}
