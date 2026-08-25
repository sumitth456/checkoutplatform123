<?php

namespace App\Modules\Order\Services;

use App\Modules\Order\Contracts\CheckoutPaymentServiceInterface;
use App\Modules\Order\DTOs\CheckoutPaymentResultDTO;
use App\Modules\Order\DTOs\PaymentIntentDTO;
use App\Modules\Payment\Contracts\PaymentLedgerInterface;
use App\Modules\Payment\DTOs\PaymentRequest;
use App\Modules\Payment\DTOs\PaymentResponse;
use App\Modules\Payment\Enums\LedgerEntryType;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Services\PaymentOrchestrator;
use Illuminate\Support\Str;

class CheckoutPaymentService implements CheckoutPaymentServiceInterface
{
    public function __construct(
        private readonly StripePaymentIntentService $paymentIntents,
        private readonly PaymentOrchestrator $orchestrator,
        private readonly PaymentLedgerInterface $ledger,
    ) {}

    public function createPaymentIntent(
        string $amount,
        string $currency,
        array $metadata = [],
        ?string $idempotencyKey = null,
    ): PaymentIntentDTO {
        return $this->paymentIntents->create($amount, $currency, $metadata);
    }

    public function authorize(PaymentRequest $request): PaymentResponse
    {
        return $this->orchestrator->processPayment($request);
    }

    public function capture(
        string $transactionId,
        string $amount,
        string $currency,
        ?string $provider = null,
        ?string $idempotencyKey = null,
    ): PaymentResponse {
        return $this->orchestrator->capture(
            transactionId: $transactionId,
            amount: $amount,
            currency: $currency,
            provider: $provider,
            idempotencyKey: $idempotencyKey,
        );
    }

    public function processAuthorizeAndCapture(PaymentRequest $request): CheckoutPaymentResultDTO
    {
        if ((bool) config('payment.sandbox_mode', false)) {
            return $this->processSandboxAuthorizeAndCapture($request);
        }

        $metadata = array_merge($request->metadata, [
            'customer_id' => $request->customerId,
            'checkout_reference' => $request->orderId,
        ]);

        $intent = $this->paymentIntents->create(
            amount: $request->amount,
            currency: $request->currency,
            metadata: $metadata,
        );

        $confirmed = $this->paymentIntents->confirm($intent->id, $request->paymentToken);

        $authorizeResponse = new PaymentResponse(
            status: PaymentStatus::Authorized,
            transactionId: $intent->id,
            gatewayReference: (string) ($confirmed['client_secret'] ?? $intent->clientSecret),
            provider: PaymentProvider::Stripe,
            amount: $request->amount,
            currency: $request->currency,
            raw: $confirmed,
        );

        $this->ledger->record(
            entryType: LedgerEntryType::Authorize,
            provider: PaymentProvider::Stripe,
            statusTo: PaymentStatus::Authorized,
            response: $authorizeResponse,
            statusFrom: PaymentStatus::Pending,
            idempotencyKey: $request->idempotencyKey,
        );

        $captureResponse = $this->orchestrator->capture(
            transactionId: $intent->id,
            amount: $request->amount,
            currency: $request->currency,
            provider: $request->provider?->value,
            idempotencyKey: $request->idempotencyKey.'-capture',
        );

        return new CheckoutPaymentResultDTO(
            paymentIntent: $intent,
            authorizeResponse: $authorizeResponse,
            captureResponse: $captureResponse,
        );
    }

    private function processSandboxAuthorizeAndCapture(PaymentRequest $request): CheckoutPaymentResultDTO
    {
        $intentId = 'pi_sandbox_'.Str::uuid();
        $provider = $request->provider ?? PaymentProvider::Stripe;

        $intent = new PaymentIntentDTO(
            id: $intentId,
            clientSecret: 'sandbox_secret_'.$intentId,
            status: 'succeeded',
            amount: $request->amount,
            currency: $request->currency,
            raw: ['sandbox' => true, 'payment_method' => $request->paymentToken],
        );

        $authorizeResponse = new PaymentResponse(
            status: PaymentStatus::Authorized,
            transactionId: $intentId,
            gatewayReference: $intent->clientSecret,
            provider: $provider,
            amount: $request->amount,
            currency: $request->currency,
            raw: ['sandbox' => true],
            metadata: ['sandbox' => true],
        );

        $this->ledger->record(
            entryType: LedgerEntryType::Authorize,
            provider: $provider,
            statusTo: PaymentStatus::Authorized,
            response: $authorizeResponse,
            statusFrom: PaymentStatus::Pending,
            idempotencyKey: $request->idempotencyKey,
        );

        $captureResponse = new PaymentResponse(
            status: PaymentStatus::Captured,
            transactionId: $intentId,
            gatewayReference: $intentId,
            provider: $provider,
            amount: $request->amount,
            currency: $request->currency,
            raw: ['sandbox' => true, 'status' => 'succeeded'],
            metadata: ['sandbox' => true],
        );

        $this->ledger->record(
            entryType: LedgerEntryType::Capture,
            provider: $provider,
            statusTo: PaymentStatus::Captured,
            response: $captureResponse,
            statusFrom: PaymentStatus::Authorized,
            idempotencyKey: $request->idempotencyKey.'-capture',
        );

        return new CheckoutPaymentResultDTO(
            paymentIntent: $intent,
            authorizeResponse: $authorizeResponse,
            captureResponse: $captureResponse,
        );
    }
}
