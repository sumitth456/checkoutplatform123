<?php

namespace App\Modules\Payment\Decorators;

use App\Modules\Payment\Contracts\IPaymentGateway;
use App\Modules\Payment\DTOs\PaymentRequest;
use App\Modules\Payment\DTOs\PaymentResponse;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Resilience\CircuitBreaker;
use Throwable;

class ResilientGatewayDecorator implements IPaymentGateway
{
    public function __construct(
        private readonly IPaymentGateway $gateway,
        private readonly CircuitBreaker $circuitBreaker,
    ) {}

    public function provider(): PaymentProvider
    {
        return $this->gateway->provider();
    }

    public function authorize(PaymentRequest $request): PaymentResponse
    {
        return $this->execute(fn () => $this->gateway->authorize($request));
    }

    public function capture(string $transactionId, string $amount, string $currency): PaymentResponse
    {
        return $this->execute(fn () => $this->gateway->capture($transactionId, $amount, $currency));
    }

    public function refund(string $transactionId, string $amount, string $currency): PaymentResponse
    {
        return $this->execute(fn () => $this->gateway->refund($transactionId, $amount, $currency));
    }

    public function voidTransaction(string $transactionId): PaymentResponse
    {
        return $this->execute(fn () => $this->gateway->voidTransaction($transactionId));
    }

    public function getStatus(string $transactionId): PaymentStatus
    {
        return $this->execute(fn () => $this->gateway->getStatus($transactionId));
    }

    private function execute(callable $callback): mixed
    {
        $this->circuitBreaker->assertClosed($this->provider());

        $resilience = app(\App\Modules\Admin\Services\PlatformConfigResolver::class)->paymentResilience();
        $attempts = $resilience['max_attempts'];
        $delayMs = $resilience['retry_delay_ms'];
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $result = $callback();
                $this->circuitBreaker->recordSuccess($this->provider());

                return $result;
            } catch (Throwable $exception) {
                $lastException = $exception;
                $this->circuitBreaker->recordFailure($this->provider());

                if ($attempt < $attempts) {
                    usleep($delayMs * 1000 * $attempt);
                }
            }
        }

        throw $lastException;
    }
}
