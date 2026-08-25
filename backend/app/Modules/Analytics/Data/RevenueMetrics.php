<?php

namespace App\Modules\Analytics\Data;

readonly class RevenueMetrics
{
    public function __construct(
        public string $total,
        public int $transactionCount,
        public string $averageOrderValue,
        public string $currency,
        public string $periodFrom,
        public string $periodTo,
    ) {}

    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'transaction_count' => $this->transactionCount,
            'average_order_value' => $this->averageOrderValue,
            'currency' => $this->currency,
            'period' => [
                'from' => $this->periodFrom,
                'to' => $this->periodTo,
            ],
        ];
    }
}
