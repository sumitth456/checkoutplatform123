<?php

namespace App\Modules\Analytics\Data;

readonly class FailedPaymentMetrics
{
    public function __construct(
        public int $failedCount,
        public string $failedAmount,
        public int $billingFailureCount,
        public string $currency,
        public string $periodFrom,
        public string $periodTo,
    ) {}

    public function toArray(): array
    {
        return [
            'failed_count' => $this->failedCount,
            'failed_amount' => $this->failedAmount,
            'billing_failure_count' => $this->billingFailureCount,
            'currency' => $this->currency,
            'period' => [
                'from' => $this->periodFrom,
                'to' => $this->periodTo,
            ],
        ];
    }
}
