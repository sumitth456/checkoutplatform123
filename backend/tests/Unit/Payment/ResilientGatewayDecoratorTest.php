<?php

namespace Tests\Unit\Payment;

use App\Modules\Payment\Contracts\IPaymentGateway;
use App\Modules\Payment\Decorators\ResilientGatewayDecorator;
use App\Modules\Payment\DTOs\PaymentRequest;
use App\Modules\Payment\DTOs\PaymentResponse;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Exceptions\PaymentGatewayException;
use App\Modules\Payment\Resilience\CircuitBreaker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResilientGatewayDecoratorTest extends TestCase
{
    #[Test]
    public function it_retries_transient_failures_before_succeeding(): void
    {
        config([
            'payment.resilience.max_attempts' => 3,
            'payment.resilience.retry_delay_ms' => 0,
        ]);

        $attempts = 0;
        $inner = new class($attempts) implements IPaymentGateway
        {
            public function __construct(private int &$attempts) {}

            public function provider(): PaymentProvider
            {
                return PaymentProvider::Stripe;
            }

            public function authorize(PaymentRequest $request): PaymentResponse
            {
                $this->attempts++;

                if ($this->attempts < 3) {
                    throw PaymentGatewayException::transportFailure(
                        provider: PaymentProvider::Stripe,
                        message: 'temporary outage',
                    );
                }

                return new PaymentResponse(
                    status: PaymentStatus::Authorized,
                    transactionId: 'txn_retry_ok',
                    gatewayReference: 'ref',
                    provider: PaymentProvider::Stripe,
                    amount: $request->amount,
                    currency: $request->currency,
                );
            }

            public function capture(string $transactionId, string $amount, string $currency): PaymentResponse
            {
                throw new \BadMethodCallException('not used');
            }

            public function refund(string $transactionId, string $amount, string $currency): PaymentResponse
            {
                throw new \BadMethodCallException('not used');
            }

            public function voidTransaction(string $transactionId): PaymentResponse
            {
                throw new \BadMethodCallException('not used');
            }

            public function getStatus(string $transactionId): PaymentStatus
            {
                throw new \BadMethodCallException('not used');
            }
        };

        $decorator = new ResilientGatewayDecorator($inner, app(CircuitBreaker::class));

        $response = $decorator->authorize(new PaymentRequest(
            amount: '5.00',
            currency: 'USD',
            paymentToken: 'tok_test',
        ));

        $this->assertSame(3, $attempts);
        $this->assertSame('txn_retry_ok', $response->transactionId);
    }

    #[Test]
    public function it_opens_circuit_after_threshold_failures(): void
    {
        config([
            'payment.resilience.max_attempts' => 1,
            'payment.resilience.circuit_failure_threshold' => 2,
            'payment.resilience.retry_delay_ms' => 0,
        ]);

        $inner = new class implements IPaymentGateway
        {
            public function provider(): PaymentProvider
            {
                return PaymentProvider::Stripe;
            }

            public function authorize(PaymentRequest $request): PaymentResponse
            {
                throw PaymentGatewayException::transportFailure(
                    provider: PaymentProvider::Stripe,
                    message: 'down',
                );
            }

            public function capture(string $transactionId, string $amount, string $currency): PaymentResponse
            {
                throw new \BadMethodCallException('not used');
            }

            public function refund(string $transactionId, string $amount, string $currency): PaymentResponse
            {
                throw new \BadMethodCallException('not used');
            }

            public function voidTransaction(string $transactionId): PaymentResponse
            {
                throw new \BadMethodCallException('not used');
            }

            public function getStatus(string $transactionId): PaymentStatus
            {
                throw new \BadMethodCallException('not used');
            }
        };

        $breaker = app(CircuitBreaker::class);
        $decorator = new ResilientGatewayDecorator($inner, $breaker);

        try {
            $decorator->authorize(new PaymentRequest(
                amount: '5.00',
                currency: 'USD',
                paymentToken: 'tok_test',
            ));
        } catch (PaymentGatewayException) {
        }

        try {
            $decorator->authorize(new PaymentRequest(
                amount: '5.00',
                currency: 'USD',
                paymentToken: 'tok_test',
            ));
        } catch (PaymentGatewayException) {
        }

        $this->assertTrue($breaker->isOpen(PaymentProvider::Stripe));

        $this->expectException(PaymentGatewayException::class);
        $decorator->authorize(new PaymentRequest(
            amount: '5.00',
            currency: 'USD',
            paymentToken: 'tok_test',
        ));
    }
}
