<?php

namespace App\Modules\Admin\Data;

readonly class DashboardMetrics
{
    /**
     * @param  array<int, array{label: string, revenue: float, orders: int}>  $revenueTrend
     * @param  array<int, array{name: string, success_rate: float, success: int, failed: int}>  $gatewayHealth
     * @param  array<int, array{name: string, orders: int, revenue: float}>  $topProducts
     * @param  array<int, array{name: string, orders: int, revenue: float}>  $topFunnels
     */
    public function __construct(
        public int $todayOrders,
        public float $todayRevenue,
        public float $monthlyRevenue,
        public int $activeSubscriptions,
        public int $pendingRebill,
        public int $failedPaymentsToday,
        public int $pendingFulfillment,
        public int $delayedShipments,
        public int $refundsToday,
        public float $refundsAmountToday,
        public int $chargebacksPeriod,
        public float $chargebacksAmountPeriod,
        public float $gatewaySuccessRate,
        public string $currency,
        public array $revenueTrend,
        public array $gatewayHealth,
        public array $topProducts,
        public array $topFunnels,
    ) {}
}
