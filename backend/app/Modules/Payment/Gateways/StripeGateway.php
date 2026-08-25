<?php

namespace App\Modules\Payment\Gateways;

use App\Modules\Payment\DTOs\PaymentRequest;
use App\Modules\Payment\DTOs\PaymentResponse;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Exceptions\PaymentGatewayException;

class StripeGateway extends AbstractPaymentGateway
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::Stripe;
    }

    protected function providerEndpoint(string $action): string
    {
        return match ($action) {
            'authorize' => '/payment_intents',
            'capture' => '/payment_intents/capture',
            'refund' => '/refunds',
            'void' => '/payment_intents/cancel',
            'status' => '/payment_intents/status',
        };
    }

    protected function buildAuthorizePayload(PaymentRequest $request): array
    {
        return [
            'amount' => $this->toMinorUnits($request->amount),
            'currency' => strtolower($request->currency),
            'payment_method' => $request->paymentToken,
            'capture_method' => 'manual',
            'confirm' => true,
            'metadata' => $this->stringifyMetadata($request->metadata),
        ];
    }

    public function capture(string $transactionId, string $amount, string $currency): PaymentResponse
    {
        $response = $this->httpClient()->post("/payment_intents/{$transactionId}/capture", [
            'amount_to_capture' => $this->toMinorUnits($amount),
        ]);

        if ($response->failed()) {
            throw PaymentGatewayException::transportFailure(
                provider: $this->provider(),
                message: $response->json('error.message') ?? $response->body(),
                context: ['action' => 'capture', 'transaction_id' => $transactionId],
            );
        }

        return $this->normalizeCaptureResponse($response->json() ?? [], $transactionId, $amount, $currency);
    }

    public function refund(string $transactionId, string $amount, string $currency): PaymentResponse
    {
        $response = $this->httpClient()->post('/refunds', [
            'payment_intent' => $transactionId,
            'amount' => $this->toMinorUnits($amount),
        ]);

        if ($response->failed()) {
            throw PaymentGatewayException::transportFailure(
                provider: $this->provider(),
                message: $response->json('error.message') ?? $response->body(),
                context: ['action' => 'refund', 'transaction_id' => $transactionId],
            );
        }

        return $this->normalizeRefundResponse($response->json() ?? [], $transactionId, $amount, $currency);
    }

    public function voidTransaction(string $transactionId): PaymentResponse
    {
        $response = $this->httpClient()->post("/payment_intents/{$transactionId}/cancel");

        if ($response->failed()) {
            throw PaymentGatewayException::transportFailure(
                provider: $this->provider(),
                message: $response->json('error.message') ?? $response->body(),
                context: ['action' => 'void', 'transaction_id' => $transactionId],
            );
        }

        return $this->normalizeVoidResponse($response->json() ?? [], $transactionId);
    }

    public function getStatus(string $transactionId): PaymentStatus
    {
        $response = $this->httpClient()->get("/payment_intents/{$transactionId}");

        if ($response->failed()) {
            throw PaymentGatewayException::transportFailure(
                provider: $this->provider(),
                message: $response->json('error.message') ?? $response->body(),
                context: ['action' => 'status', 'transaction_id' => $transactionId],
            );
        }

        return $this->normalizeStatusResponse($response->json() ?? [], $transactionId);
    }

    protected function normalizeAuthorizeResponse(array $raw, PaymentRequest $request): PaymentResponse
    {
        return $this->successResponse(
            status: PaymentStatus::Authorized,
            transactionId: (string) ($raw['id'] ?? $this->makeTransactionId('pi')),
            gatewayReference: (string) ($raw['client_secret'] ?? $raw['id'] ?? ''),
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
        return match ($raw['status'] ?? 'pending') {
            'requires_capture', 'authorized' => PaymentStatus::Authorized,
            'succeeded' => PaymentStatus::Captured,
            'canceled' => PaymentStatus::Voided,
            default => PaymentStatus::Pending,
        };
    }

    protected function httpClient(): \Illuminate\Http\Client\PendingRequest
    {
        return parent::httpClient()->withToken((string) ($this->config['secret_key'] ?? 'sk_test'));
    }

    /** @param array<string, mixed> $metadata */
    private function stringifyMetadata(array $metadata): array
    {
        $normalized = [];

        foreach ($metadata as $key => $value) {
            if ($value === null) {
                continue;
            }

            $normalized[(string) $key] = is_scalar($value) ? (string) $value : json_encode($value);
        }

        return $normalized;
    }

    private function toMinorUnits(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
