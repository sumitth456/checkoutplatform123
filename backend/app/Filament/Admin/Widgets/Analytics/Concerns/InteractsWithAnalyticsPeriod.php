<?php

namespace App\Filament\Admin\Widgets\Analytics\Concerns;

use App\Filament\Admin\Support\Concerns\InteractsWithDashboardFilters;
use App\Modules\Admin\Services\AdminAnalyticsService;
use App\Modules\Analytics\Data\AnalyticsPeriod;

trait InteractsWithAnalyticsPeriod
{
    use InteractsWithDashboardFilters;

    public ?string $quickRange = null;

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?string $currency = 'USD';

    public ?int $campaignId = null;

    public ?string $campaignCategory = null;

    public ?string $trafficSource = null;

    public ?int $affiliateId = null;

    public ?int $paymentGatewayId = null;

    public ?int $fulfillmentHouseId = null;

    protected function analyticsPeriod(): AnalyticsPeriod
    {
        return AnalyticsPeriod::fromRequest(
            $this->dateFrom ?? request()->query('df_from') ?? request()->query('from'),
            $this->dateTo ?? request()->query('df_to') ?? request()->query('to'),
            $this->currency ?? request()->query('df_currency', request()->query('currency', 'USD')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function analyticsSnapshot(): array
    {
        return app(AdminAnalyticsService::class)->snapshot($this->analyticsPeriod());
    }
}
