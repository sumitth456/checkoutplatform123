<?php

namespace App\Modules\Ach\Services;

use App\Modules\Ach\Enums\AchProviderStatus;
use App\Models\AchProvider;
use App\Models\CustomerPaymentMethod;
use App\Modules\Ach\Factory\AchGatewayFactory;
use App\Modules\Order\DTOs\CheckoutPaymentResultDTO;
use App\Modules\Order\DTOs\PaymentIntentDTO;
use App\Modules\Payment\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\DTOs\PaymentRequest;
use App\Modules\Payment\DTOs\PaymentResponse;
use App\Modules\Payment\Enums\LedgerEntryType;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Contracts\PaymentLedgerInterface;
use App\Modules\Payment\Exceptions\PaymentGatewayException;
use Illuminate\Support\Str;

final class AchCheckoutPaymentService
{
    public function __construct(
        private readonly AchGatewayFactory $factory,
        private readonly AchPaymentLogger $logger,
        private readonly PaymentLedgerInterface $ledger,
    ) {}

    public function processAuthorizeAndCapture(PaymentRequest $request): CheckoutPaymentResultDTO
    {
        $provider = $this->resolveProviderFromToken($request->paymentToken);
        $gateway = $this->factory->forProvider($provider);

        $paymentMethod = CustomerPaymentMethod::query()
            ->where('payment_token', $request->paymentToken)
            ->first();

        try {
            $authorizeResponse = $gateway->authorize($request);

            $this->ledger->record(
                entryType: LedgerEntryType::Authorize,
                provider: $authorizeResponse->provider,
                statusTo: PaymentStatus::Authorized,
                response: $authorizeResponse,
                statusFrom: PaymentStatus::Pending,
                idempotencyKey: $request->idempotencyKey,
            );

            $captureResponse = $gateway->capture(
                transactionId: $authorizeResponse->transactionId,
                amount: $request->amount,
                currency: $request->currency,
            );

            $captureResponse = new PaymentResponse(
                status: $captureResponse->status,
                transactionId: $captureResponse->transactionId,
                gatewayReference: $captureResponse->gatewayReference,
                provider: $captureResponse->provider,
                amount: $captureResponse->amount,
                currency: $captureResponse->currency,
                raw: $captureResponse->raw,
                metadata: array_merge($captureResponse->metadata, [
                    'payment_method' => 'ach',
                    'authorization_id' => $authorizeResponse->metadata['authorization_id'] ?? $authorizeResponse->gatewayReference,
                    'account_type' => $paymentMethod?->account_type?->value ?? ($request->metadata['account_type'] ?? null),
                    'last4' => $paymentMethod?->last4 ?? ($request->metadata['last4'] ?? null),
                    'ach_provider_id' => $provider->id,
                ]),
            );

            $this->ledger->record(
                entryType: LedgerEntryType::Capture,
                provider: $captureResponse->provider,
                statusTo: PaymentStatus::Captured,
                response: $captureResponse,
                statusFrom: PaymentStatus::Authorized,
                idempotencyKey: ($request->idempotencyKey ?? '').'-capture',
            );

            $this->logger->log(
                action: 'capture',
                status: 'success',
                request: ['amount' => $request->amount, 'payment_token' => $request->paymentToken],
                response: $captureResponse->toArray(),
                provider: $provider,
                customerId: $request->customerId ? (int) $request->customerId : null,
                idempotencyKey: $request->idempotencyKey,
            );

            $intent = new PaymentIntentDTO(
                id: $authorizeResponse->transactionId,
                clientSecret: $authorizeResponse->gatewayReference,
                status: 'succeeded',
                amount: $request->amount,
                currency: $request->currency,
                raw: ['payment_method' => 'ach'],
            );

            return new CheckoutPaymentResultDTO(
                paymentIntent: $intent,
                authorizeResponse: $authorizeResponse,
                captureResponse: $captureResponse,
            );
        } catch (PaymentGatewayException $exception) {
            $this->logger->log(
                action: 'authorize',
                status: 'failed',
                request: ['amount' => $request->amount, 'payment_token' => $request->paymentToken],
                response: ['error' => $exception->getMessage()],
                provider: $provider,
                customerId: $request->customerId ? (int) $request->customerId : null,
                idempotencyKey: $request->idempotencyKey,
            );

            throw $exception;
        }
    }

    public function refund(
        string $transactionId,
        string $amount,
        string $currency,
        ?AchProvider $provider = null,
    ): PaymentResponse {
        $gateway = $provider !== null
            ? $this->factory->forProvider($provider)
            : $this->factory->defaultGateway();

        $response = $gateway->refund($transactionId, $amount, $currency);

        $this->logger->log(
            action: 'refund',
            status: 'success',
            request: compact('transactionId', 'amount', 'currency'),
            response: $response->toArray(),
            provider: $provider,
        );

        return $response;
    }

    private function resolveProviderFromToken(string $paymentToken): AchProvider
    {
        $method = CustomerPaymentMethod::query()
            ->with('achProvider')
            ->where('payment_token', $paymentToken)
            ->first();

        if ($method?->achProvider !== null) {
            return $method->achProvider;
        }

        $default = AchProvider::query()
            ->where('status', AchProviderStatus::Enabled)
            ->orderByDesc('is_default')
            ->first();

        if ($default !== null) {
            return $default;
        }

        throw PaymentGatewayException::transportFailure(
            provider: PaymentProvider::SandboxAch,
            message: 'Unable to resolve ACH provider for payment token.',
            context: ['action' => 'authorize'],
        );
    }
}
