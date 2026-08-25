<?php

namespace App\Filament\Admin\Support\Concerns;

use App\Modules\Admin\Data\DashboardFilter;
use App\Modules\Admin\Services\DashboardFilterUrlBuilder;
use App\Modules\Admin\Services\GlobalDashboardFilterService;

trait InteractsWithDashboardFilters
{
    protected function dashboardFilter(): DashboardFilter
    {
        return app(GlobalDashboardFilterService::class)->resolve(
            quickRange: $this->quickRange ?? request()->query('df_range', 'last_30_days'),
            from: $this->dateFrom ?? request()->query('df_from') ?? request()->query('from'),
            to: $this->dateTo ?? request()->query('df_to') ?? request()->query('to'),
            currency: $this->currency ?? request()->query('df_currency', request()->query('currency', 'USD')),
            campaignId: $this->campaignId ?? (request()->integer('df_campaign') ?: null),
            campaignCategory: $this->campaignCategory ?? request()->query('df_category'),
            trafficSource: $this->trafficSource ?? request()->query('df_traffic'),
            affiliateId: $this->affiliateId ?? (request()->integer('df_affiliate') ?: null),
            paymentGatewayId: $this->paymentGatewayId ?? (request()->integer('df_gateway') ?: null),
            fulfillmentHouseId: $this->fulfillmentHouseId ?? (request()->integer('df_fhouse') ?: null),
        );
    }

    protected function dashboardUrls(): DashboardFilterUrlBuilder
    {
        return app(DashboardFilterUrlBuilder::class);
    }
}
