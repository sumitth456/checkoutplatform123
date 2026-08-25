<?php

namespace App\Modules\Ach\Gateways;

use App\Modules\Ach\DTOs\AchTokenizeRequest;
use App\Modules\Ach\DTOs\AchTokenizeResponse;
use App\Modules\Ach\Enums\AchProviderType;
use App\Modules\Ach\Support\AchDataMasker;
use App\Modules\Payment\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\DTOs\PaymentRequest;
use App\Modules\Payment\DTOs\PaymentResponse;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Exceptions\PaymentGatewayException;
use Illuminate\Support\Str;

abstract class AbstractAchGateway implements PaymentGatewayInterface
{
    public function __construct(
        protected array $config = [],
    ) {}

    abstract public function provider(): PaymentProvider;

    abstract protected function providerType(): AchProviderType;

    public function tokenize(AchTokenizeRequest $request): AchTokenizeResponse
    {
        return $this->performTokenize($request);
    }

    abstract protected function performTokenize(AchTokenizeRequest $request): AchTokenizeResponse;

    public function authorize(PaymentRequest $request): PaymentResponse
    {
        $transactionId = $this->makeTransactionId('auth');
        $authorizationId = 'auth_'.Str::uuid();

        return new PaymentResponse(
            status: PaymentStatus::Authorized,
            transactionId: $transactionId,
            gatewayReference: $authorizationId,
            provider: $this->provider(),
            amount: $request->amount,
            currency: $request->currency,
            raw: [
                'payment_token' => AchDataMasker::maskToken($request->paymentToken),
                'authorization_id' => $authorizationId,
            ],
            metadata: array_merge($request->metadata, [
                'payment_method' => 'ach',
                'authorization_id' => $authorizationId,
            ]),
        );
    }

    public function capture(string $transactionId, string $amount, string $currency): PaymentResponse
    {
        return new PaymentResponse(
            status: PaymentStatus::Captured,
            transactionId: $transactionId,
            gatewayReference: $transactionId,
            provider: $this->provider(),
            amount: $amount,
            currency: $currency,
            raw: ['status' => 'captured'],
            metadata: ['payment_method' => 'ach'],
        );
    }

    public function refund(string $transactionId, string $amount, string $currency): PaymentResponse
    {
        $refundId = 're_ach_'.Str::uuid();

        return new PaymentResponse(
            status: PaymentStatus::Refunded,
            transactionId: $transactionId,
            gatewayReference: $refundId,
            provider: $this->provider(),
            amount: $amount,
            currency: $currency,
            raw: ['refund_id' => $refundId, 'status' => 'refunded'],
            metadata: [
                'payment_method' => 'ach',
                'refund_id' => $refundId,
                'refunded_at' => now()->toIso8601String(),
            ],
        );
    }

    public function voidTransaction(string $transactionId): PaymentResponse
    {
        return new PaymentResponse(
            status: PaymentStatus::Voided,
            transactionId: $transactionId,
            gatewayReference: $transactionId,
            provider: $this->provider(),
            amount: '0.00',
            currency: 'USD',
            raw: ['status' => 'voided'],
            metadata: ['payment_method' => 'ach'],
        );
    }

    public function verify(string $transactionId): PaymentResponse
    {
        return new PaymentResponse(
            status: PaymentStatus::Captured,
            transactionId: $transactionId,
            gatewayReference: $transactionId,
            provider: $this->provider(),
            amount: '0.00',
            currency: 'USD',
            raw: ['verified' => true],
            metadata: ['payment_method' => 'ach'],
        );
    }

    public function getStatus(string $transactionId): PaymentStatus
    {
        return PaymentStatus::Captured;
    }

    protected function makeTransactionId(string $prefix): string
    {
        return $this->provider()->value.'_'.$prefix.'_'.Str::uuid();
    }

    protected function makePaymentToken(): string
    {
        return 'tok_ach_'.Str::uuid();
    }

    protected function rejectFailedTestRouting(AchTokenizeRequest $request): void
    {
        $failRouting = (string) config('ach.fail_test_routing', '000000000');

        if ($request->routingNumber === $failRouting) {
            throw PaymentGatewayException::transportFailure(
                provider: $this->provider(),
                message: 'Bank account verification failed. Please verify your routing and account numbers.',
                context: ['action' => 'tokenize'],
            );
        }
    }
}
