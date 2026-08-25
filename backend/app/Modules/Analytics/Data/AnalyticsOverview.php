<?php

namespace App\Modules\Analytics\Data;

readonly class AnalyticsOverview
{
    public function __construct(
        public RevenueMetrics $revenue,
        public MrrMetrics $mrr,
        public RefundMetrics $refunds,
        public FailedPaymentMetrics $failedPayments,
        public OrderMetrics $orders,
        public SubscriptionMetrics $subscriptions,
    ) {}

    public function toArray(): array
    {
        return [
            'revenue' => $this->revenue->toArray(),
            'mrr' => $this->mrr->toArray(),
            'refunds' => $this->refunds->toArray(),
            'failed_payments' => $this->failedPayments->toArray(),
            'orders' => $this->orders->toArray(),
            'subscriptions' => $this->subscriptions->toArray(),
        ];
    }
}
