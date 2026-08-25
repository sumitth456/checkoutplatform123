<?php

namespace App\Modules\Payment\Gateways;

use App\Modules\Payment\Contracts\IPaymentGateway;
use App\Modules\Payment\DTOs\PaymentRequest;
use App\Modules\Payment\DTOs\PaymentResponse;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Exceptions\PaymentGatewayException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

abstract class AbstractPaymentGateway implements IPaymentGateway
{
    public function __construct(
        protected array $config = [],
    ) {}

    abstract protected function providerEndpoint(string $action): string;

    abstract protected function buildAuthorizePayload(PaymentRequest $request): array;

    abstract protected function normalizeAuthorizeResponse(array $raw, PaymentRequest $request): PaymentResponse;

    abstract protected function normalizeCaptureResponse(array $raw, string $transactionId, string $amount, string $currency): PaymentResponse;

    abstract protected function normalizeRefundResponse(array $raw, string $transactionId, string $amount, string $currency): PaymentResponse;

    abstract protected function normalizeVoidResponse(array $raw, string $transactionId): PaymentResponse;

    abstract protected function normalizeStatusResponse(array $raw, string $transactionId): PaymentStatus;

    public function authorize(PaymentRequest $request): PaymentResponse
    {
        $raw = $this->request('authorize', $this->buildAuthorizePayload($request));

        return $this->normalizeAuthorizeResponse($raw, $request);
    }

    public function capture(string $transactionId, string $amount, string $currency): PaymentResponse
    {
        $raw = $this->request('capture', [
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'currency' => $currency,
        ]);

        return $this->normalizeCaptureResponse($raw, $transactionId, $amount, $currency);
    }

    public function refund(string $transactionId, string $amount, string $currency): PaymentResponse
    {
        $raw = $this->request('refund', [
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'currency' => $currency,
        ]);

        return $this->normalizeRefundResponse($raw, $transactionId, $amount, $currency);
    }

    public function voidTransaction(string $transactionId): PaymentResponse
    {
        $raw = $this->request('void', [
            'transaction_id' => $transactionId,
        ]);

        return $this->normalizeVoidResponse($raw, $transactionId);
    }

    public function getStatus(string $transactionId): PaymentStatus
    {
        $raw = $this->request('status', [
            'transaction_id' => $transactionId,
        ]);

        return $this->normalizeStatusResponse($raw, $transactionId);
    }

    protected function request(string $action, array $payload): array
    {
        $response = $this->httpClient()
            ->post($this->providerEndpoint($action), $payload);

        if ($response->failed()) {
            throw PaymentGatewayException::transportFailure(
                provider: $this->provider(),
                message: $response->json('message') ?? $response->body(),
                context: ['action' => $action, 'status' => $response->status()],
            );
        }

        return $response->json() ?? [];
    }

    protected function httpClient(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) ($this->config['base_url'] ?? ''), '/'))
            ->timeout((int) config('payment.resilience.timeout_seconds', 30))
            ->acceptJson()
            ->asJson();
    }

    protected function makeTransactionId(?string $prefix = null): string
    {
        return ($prefix ?? $this->provider()->value).'_'.Str::uuid()->toString();
    }

    protected function successResponse(
        PaymentStatus $status,
        string $transactionId,
        string $gatewayReference,
        string $amount,
        string $currency,
        array $raw = [],
    ): PaymentResponse {
        return new PaymentResponse(
            status: $status,
            transactionId: $transactionId,
            gatewayReference: $gatewayReference,
            provider: $this->provider(),
            amount: $amount,
            currency: $currency,
            raw: $raw,
        );
    }
}
