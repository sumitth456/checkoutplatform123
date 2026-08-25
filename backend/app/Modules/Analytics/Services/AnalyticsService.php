<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Contracts\AnalyticsRepositoryInterface;
use App\Modules\Analytics\Data\AnalyticsOverview;
use App\Modules\Analytics\Data\AnalyticsPeriod;
use App\Modules\Analytics\Data\FailedPaymentMetrics;
use App\Modules\Analytics\Data\MrrMetrics;
use App\Modules\Analytics\Data\OrderMetrics;
use App\Modules\Analytics\Data\RefundMetrics;
use App\Modules\Analytics\Data\RevenueMetrics;
use App\Modules\Analytics\Data\SubscriptionMetrics;

class AnalyticsService
{
    public function __construct(
        private readonly AnalyticsRepositoryInterface $analytics,
    ) {}

    public function overview(AnalyticsPeriod $period): AnalyticsOverview
    {
        return new AnalyticsOverview(
            revenue: $this->revenue($period),
            mrr: $this->mrr($period),
            refunds: $this->refunds($period),
            failedPayments: $this->failedPayments($period),
            orders: $this->orders($period),
            subscriptions: $this->subscriptions($period),
        );
    }

    public function revenue(AnalyticsPeriod $period): RevenueMetrics
    {
        $data = $this->analytics->revenueTotal($period);
        $total = (float) $data['total'];
        $count = (int) $data['count'];

        return new RevenueMetrics(
            total: $this->formatMoney($total),
            transactionCount: $count,
            averageOrderValue: $this->formatMoney($count > 0 ? $total / $count : 0),
            currency: (string) $data['currency'],
            periodFrom: $period->from->toIso8601String(),
            periodTo: $period->to->toIso8601String(),
        );
    }

    public function mrr(AnalyticsPeriod $period): MrrMetrics
    {
        $billings = $this->analytics->activeSubscriptionBilling($period);
        $mrr = 0.0;
        $currency = $period->currency ?? 'USD';

        foreach ($billings as $billing) {
            $monthly = $billing['amount'] * (30 / $billing['interval_days']) * $billing['quantity'];
            $mrr += $monthly;
            $currency = $billing['currency'];
        }

        return new MrrMetrics(
            mrr: $this->formatMoney($mrr),
            arr: $this->formatMoney($mrr * 12),
            activeSubscriptions: count($billings),
            currency: $currency,
            asOf: now()->toIso8601String(),
        );
    }

    public function refunds(AnalyticsPeriod $period): RefundMetrics
    {
        $refundData = $this->analytics->refundTotal($period);
        $revenueData = $this->analytics->revenueTotal($period);

        $refunded = (float) $refundData['total'];
        $revenue = (float) $revenueData['total'];
        $rate = $revenue > 0 ? ($refunded / $revenue) * 100 : 0;

        return new RefundMetrics(
            totalRefunded: $this->formatMoney($refunded),
            refundCount: (int) $refundData['count'],
            refundRate: number_format($rate, 2, '.', '').'%',
            currency: (string) $refundData['currency'],
            periodFrom: $period->from->toIso8601String(),
            periodTo: $period->to->toIso8601String(),
        );
    }

    public function failedPayments(AnalyticsPeriod $period): FailedPaymentMetrics
    {
        $data = $this->analytics->failedPaymentTotal($period);

        return new FailedPaymentMetrics(
            failedCount: (int) $data['failed_count'],
            failedAmount: $this->formatMoney((float) $data['failed_amount']),
            billingFailureCount: (int) $data['billing_failure_count'],
            currency: (string) $data['currency'],
            periodFrom: $period->from->toIso8601String(),
            periodTo: $period->to->toIso8601String(),
        );
    }

    public function orders(AnalyticsPeriod $period): OrderMetrics
    {
        $data = $this->analytics->orderSummary($period);

        return new OrderMetrics(
            totalOrders: (int) $data['total_orders'],
            newOrders: (int) $data['new_orders'],
            grossMerchandiseValue: $this->formatMoney((float) $data['gmv']),
            netRevenue: $this->formatMoney((float) $data['net_revenue']),
            byStatus: $data['by_status'],
            byPaymentStatus: $data['by_payment_status'],
            currency: (string) $data['currency'],
            periodFrom: $period->from->toIso8601String(),
            periodTo: $period->to->toIso8601String(),
        );
    }

    public function subscriptions(AnalyticsPeriod $period): SubscriptionMetrics
    {
        $data = $this->analytics->subscriptionSummary($period);

        return new SubscriptionMetrics(
            totalSubscriptions: (int) $data['total_subscriptions'],
            newSubscriptions: (int) $data['new_subscriptions'],
            cancelledSubscriptions: (int) $data['cancelled_subscriptions'],
            pastDueSubscriptions: (int) $data['past_due_subscriptions'],
            byStatus: $data['by_status'],
            periodFrom: $period->from->toIso8601String(),
            periodTo: $period->to->toIso8601String(),
        );
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
