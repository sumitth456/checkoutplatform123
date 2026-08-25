<?php

namespace App\Modules\Payment\Resilience;

use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Exceptions\PaymentGatewayException;
use Illuminate\Support\Facades\Cache;

class CircuitBreaker
{
    public function assertClosed(PaymentProvider $provider): void
    {
        $key = $this->openKey($provider);

        if (Cache::has($key)) {
            throw PaymentGatewayException::transportFailure(
                provider: $provider,
                message: 'Payment provider circuit breaker is open.',
                context: ['circuit' => 'open'],
            );
        }
    }

    public function recordSuccess(PaymentProvider $provider): void
    {
        Cache::forget($this->failureKey($provider));
        Cache::forget($this->openKey($provider));
    }

    public function recordFailure(PaymentProvider $provider): void
    {
        $threshold = (int) config('payment.resilience.circuit_failure_threshold', 5);
        $recoverySeconds = (int) config('payment.resilience.circuit_recovery_seconds', 60);

        $failures = (int) Cache::increment($this->failureKey($provider));

        if ($failures >= $threshold) {
            Cache::put($this->openKey($provider), true, $recoverySeconds);
        }
    }

    private function failureKey(PaymentProvider $provider): string
    {
        return 'payment:circuit:failures:'.$provider->value;
    }

    private function openKey(PaymentProvider $provider): string
    {
        return 'payment:circuit:open:'.$provider->value;
    }
}
