<?php

namespace Tests\Unit\Payment;

use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Exceptions\PaymentGatewayException;
use App\Modules\Payment\Resilience\CircuitBreaker;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    #[Test]
    public function it_opens_after_repeated_failures(): void
    {
        Cache::flush();
        $breaker = app(CircuitBreaker::class);

        for ($i = 0; $i < (int) config('payment.resilience.circuit_failure_threshold', 5); $i++) {
            $breaker->recordFailure(PaymentProvider::Stripe);
        }

        $this->expectException(PaymentGatewayException::class);
        $breaker->assertClosed(PaymentProvider::Stripe);
    }

    #[Test]
    public function it_closes_after_success(): void
    {
        Cache::flush();
        $breaker = app(CircuitBreaker::class);
        $breaker->recordFailure(PaymentProvider::Stripe);
        $breaker->recordSuccess(PaymentProvider::Stripe);

        $breaker->assertClosed(PaymentProvider::Stripe);
        $this->assertTrue(true);
    }
}
