<?php

namespace App\Filament\Admin\Widgets\RebillRecovery\Concerns;

use App\Modules\Admin\Data\DashboardFilter;
use App\Modules\Admin\Services\GlobalDashboardFilterService;
use App\Modules\Analytics\Services\RebillRecoveryAnalyticsService;

trait InteractsWithRebillRecoveryFilters
{
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

    protected function recoveryFilter(): DashboardFilter
    {
        return app(GlobalDashboardFilterService::class)->resolve(
            quickRange: $this->quickRange ?? request()->query('df_range', 'last_30_days'),
            from: $this->dateFrom ?? request()->query('df_from'),
            to: $this->dateTo ?? request()->query('df_to'),
            currency: $this->currency ?? request()->query('df_currency', 'USD'),
            campaignId: $this->campaignId ?? (request()->integer('df_campaign') ?: null),
            campaignCategory: $this->campaignCategory ?? request()->query('df_category'),
            trafficSource: $this->trafficSource ?? request()->query('df_traffic'),
            affiliateId: $this->affiliateId ?? (request()->integer('df_affiliate') ?: null),
            paymentGatewayId: $this->paymentGatewayId ?? (request()->integer('df_gateway') ?: null),
            fulfillmentHouseId: $this->fulfillmentHouseId ?? (request()->integer('df_fhouse') ?: null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function recoverySnapshot(): array
    {
        return app(RebillRecoveryAnalyticsService::class)->snapshot($this->recoveryFilter());
    }
}
