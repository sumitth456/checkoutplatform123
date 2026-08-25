<?php

namespace App\Modules\Analytics\Data;

readonly class RefundMetrics
{
    public function __construct(
        public string $totalRefunded,
        public int $refundCount,
        public string $refundRate,
        public string $currency,
        public string $periodFrom,
        public string $periodTo,
    ) {}

    public function toArray(): array
    {
        return [
            'total_refunded' => $this->totalRefunded,
            'refund_count' => $this->refundCount,
            'refund_rate' => $this->refundRate,
            'currency' => $this->currency,
            'period' => [
                'from' => $this->periodFrom,
                'to' => $this->periodTo,
            ],
        ];
    }
}
