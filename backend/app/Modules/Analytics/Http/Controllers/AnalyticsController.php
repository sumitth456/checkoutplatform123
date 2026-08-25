<?php

namespace App\Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Data\AnalyticsPeriod;
use App\Modules\Analytics\Http\Requests\AnalyticsRequest;
use App\Modules\Analytics\Http\Resources\AnalyticsOverviewResource;
use App\Modules\Analytics\Http\Resources\FailedPaymentMetricsResource;
use App\Modules\Analytics\Http\Resources\MrrMetricsResource;
use App\Modules\Analytics\Http\Resources\OrderMetricsResource;
use App\Modules\Analytics\Http\Resources\RefundMetricsResource;
use App\Modules\Analytics\Http\Resources\RevenueMetricsResource;
use App\Modules\Analytics\Http\Resources\SubscriptionMetricsResource;
use App\Modules\Analytics\Services\AnalyticsService;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics,
    ) {}

    public function overview(AnalyticsRequest $request): AnalyticsOverviewResource
    {
        return new AnalyticsOverviewResource(
            $this->analytics->overview($this->period($request)),
        );
    }

    public function revenue(AnalyticsRequest $request): RevenueMetricsResource
    {
        return new RevenueMetricsResource(
            $this->analytics->revenue($this->period($request)),
        );
    }

    public function mrr(AnalyticsRequest $request): MrrMetricsResource
    {
        return new MrrMetricsResource(
            $this->analytics->mrr($this->period($request)),
        );
    }

    public function refunds(AnalyticsRequest $request): RefundMetricsResource
    {
        return new RefundMetricsResource(
            $this->analytics->refunds($this->period($request)),
        );
    }

    public function failedPayments(AnalyticsRequest $request): FailedPaymentMetricsResource
    {
        return new FailedPaymentMetricsResource(
            $this->analytics->failedPayments($this->period($request)),
        );
    }

    public function orders(AnalyticsRequest $request): OrderMetricsResource
    {
        return new OrderMetricsResource(
            $this->analytics->orders($this->period($request)),
        );
    }

    public function subscriptions(AnalyticsRequest $request): SubscriptionMetricsResource
    {
        return new SubscriptionMetricsResource(
            $this->analytics->subscriptions($this->period($request)),
        );
    }

    private function period(AnalyticsRequest $request): AnalyticsPeriod
    {
        $validated = $request->validated();

        return AnalyticsPeriod::fromRequest(
            from: $validated['from'] ?? null,
            to: $validated['to'] ?? null,
            currency: isset($validated['currency']) ? strtoupper($validated['currency']) : null,
        );
    }
}
