<?php

namespace App\Modules\Analytics\Data;

readonly class OrderMetrics
{
    /**
     * @param  array<string, int>  $byStatus
     * @param  array<string, int>  $byPaymentStatus
     */
    public function __construct(
        public int $totalOrders,
        public int $newOrders,
        public string $grossMerchandiseValue,
        public string $netRevenue,
        public array $byStatus,
        public array $byPaymentStatus,
        public string $currency,
        public string $periodFrom,
        public string $periodTo,
    ) {}

    public function toArray(): array
    {
        return [
            'total_orders' => $this->totalOrders,
            'new_orders' => $this->newOrders,
            'gross_merchandise_value' => $this->grossMerchandiseValue,
            'net_revenue' => $this->netRevenue,
            'by_status' => $this->byStatus,
            'by_payment_status' => $this->byPaymentStatus,
            'currency' => $this->currency,
            'period' => [
                'from' => $this->periodFrom,
                'to' => $this->periodTo,
            ],
        ];
    }
}
