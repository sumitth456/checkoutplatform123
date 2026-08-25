<?php

namespace App\Modules\Payment\Services;

use App\Modules\Admin\Services\PlatformConfigResolver;
use App\Modules\Payment\Contracts\GatewayRoutingStrategyInterface;
use App\Modules\Payment\Contracts\IdempotencyStoreInterface;
use App\Modules\Payment\Contracts\PaymentLedgerInterface;
use App\Modules\Payment\DTOs\PaymentRequest;
use App\Modules\Payment\DTOs\PaymentResponse;
use App\Modules\Payment\DTOs\RefundRequest;
use App\Modules\Payment\Enums\LedgerEntryType;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Factory\PaymentGatewayFactory;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentOrchestrator
{
    public function __construct(
        private readonly PaymentGatewayFactory $factory,
        private readonly GatewayRoutingStrategyInterface $routingStrategy,
        private readonly IdempotencyStoreInterface $idempotencyStore,
        private readonly PaymentLedgerInterface $ledger,
        private readonly PlatformConfigResolver $platformConfig,
    ) {}

    public function processPayment(PaymentRequest $request): PaymentResponse
    {
        $idempotencyKey = $this->resolveIdempotencyKey($request);
        $requestHash = $this->hashRequest($request->toArray());

        if ($cached = $this->idempotencyStore->find($idempotencyKey, 'authorize', $requestHash)) {
            return $cached;
        }

        $provider = $this->routingStrategy->resolve($request, $request->provider);
        $gateway = $this->factory->getGateway($provider);
        $response = $gateway->authorize($request);

        $this->ledger->record(
            entryType: LedgerEntryType::Authorize,
            provider: $provider,
            statusTo: $response->status,
            response: $response,
            statusFrom: PaymentStatus::Pending,
            idempotencyKey: $idempotencyKey,
        );

        $this->idempotencyStore->store($idempotencyKey, 'authorize', $requestHash, $response);

        return $response;
    }

    public function capture(string $transactionId, string $amount, string $currency, ?string $provider = null, ?string $idempotencyKey = null): PaymentResponse
    {
        $operation = 'capture';
        $payload = compact('transactionId', 'amount', 'currency', 'provider');
        $idempotencyKey = $idempotencyKey ?? (string) Str::uuid();
        $requestHash = $this->hashRequest($payload);

        if ($cached = $this->idempotencyStore->find($idempotencyKey, $operation, $requestHash)) {
            return $cached;
        }

        $gateway = $this->factory->getGateway($this->resolveProvider($provider));
        $response = $gateway->capture($transactionId, $amount, $currency);

        $this->ledger->record(
            entryType: LedgerEntryType::Capture,
            provider: $response->provider,
            statusTo: $response->status,
            response: $response,
            statusFrom: PaymentStatus::Authorized,
            idempotencyKey: $idempotencyKey,
        );

        $this->idempotencyStore->store($idempotencyKey, $operation, $requestHash, $response);

        return $response;
    }

    public function processRefund(RefundRequest $request): PaymentResponse
    {
        $operation = 'refund';
        $payload = [
            'transaction_id' => $request->transactionId,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'provider' => $request->provider?->value,
        ];
        $idempotencyKey = $request->idempotencyKey ?? (string) Str::uuid();
        $requestHash = $this->hashRequest($payload);

        if ($cached = $this->idempotencyStore->find($idempotencyKey, $operation, $requestHash)) {
            return $cached;
        }

        $gateway = $this->factory->getGateway($this->resolveProvider($request->provider?->value));
        $response = $gateway->refund($request->transactionId, $request->amount, $request->currency);

        $this->ledger->record(
            entryType: LedgerEntryType::Refund,
            provider: $response->provider,
            statusTo: $response->status,
            response: $response,
            idempotencyKey: $idempotencyKey,
        );

        $this->idempotencyStore->store($idempotencyKey, $operation, $requestHash, $response);

        return $response;
    }

    public function voidTransaction(string $transactionId, ?string $provider = null, ?string $idempotencyKey = null): PaymentResponse
    {
        $operation = 'void';
        $payload = compact('transactionId', 'provider');
        $idempotencyKey = $idempotencyKey ?? (string) Str::uuid();
        $requestHash = $this->hashRequest($payload);

        if ($cached = $this->idempotencyStore->find($idempotencyKey, $operation, $requestHash)) {
            return $cached;
        }

        $gateway = $this->factory->getGateway($this->resolveProvider($provider));
        $response = $gateway->voidTransaction($transactionId);

        $this->ledger->record(
            entryType: LedgerEntryType::Void,
            provider: $response->provider,
            statusTo: $response->status,
            response: $response,
            statusFrom: PaymentStatus::Authorized,
            idempotencyKey: $idempotencyKey,
        );

        $this->idempotencyStore->store($idempotencyKey, $operation, $requestHash, $response);

        return $response;
    }

    public function getStatus(string $transactionId, ?string $provider = null): PaymentStatus
    {
        $gateway = $this->factory->getGateway($this->resolveProvider($provider));

        return $gateway->getStatus($transactionId);
    }

    private function resolveIdempotencyKey(PaymentRequest $request): string
    {
        if (blank($request->idempotencyKey)) {
            throw ValidationException::withMessages([
                'idempotency_key' => ['An idempotency key is required for payment processing.'],
            ]);
        }

        return $request->idempotencyKey;
    }

    private function hashRequest(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function resolveProvider(?string $provider): \App\Modules\Payment\Enums\PaymentProvider
    {
        if ($provider !== null) {
            return \App\Modules\Payment\Enums\PaymentProvider::from($provider);
        }

        return $this->platformConfig->defaultPaymentProvider();
    }
}
