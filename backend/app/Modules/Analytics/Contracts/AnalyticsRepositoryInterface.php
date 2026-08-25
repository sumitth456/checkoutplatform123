<?php

namespace App\Modules\Analytics\Contracts;

use App\Modules\Analytics\Data\AnalyticsPeriod;

interface AnalyticsRepositoryInterface
{
    public function revenueTotal(AnalyticsPeriod $period): array;

    public function refundTotal(AnalyticsPeriod $period): array;

    public function failedPaymentTotal(AnalyticsPeriod $period): array;

    public function orderSummary(AnalyticsPeriod $period): array;

    public function subscriptionSummary(AnalyticsPeriod $period): array;

    /**
     * @return list<array{amount: float, currency: string, interval_days: int|null, quantity: int}>
     */
    public function activeSubscriptionBilling(AnalyticsPeriod $period): array;
}
