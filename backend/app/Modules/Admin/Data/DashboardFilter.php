<?php

namespace App\Modules\Admin\Data;

use Carbon\CarbonInterface;

readonly class DashboardFilter
{
    public function __construct(
        public CarbonInterface $from,
        public CarbonInterface $to,
        public CarbonInterface $previousFrom,
        public CarbonInterface $previousTo,
        public string $quickRange = 'last_30_days',
        public ?string $currency = 'USD',
        public ?int $campaignId = null,
        public ?string $campaignCategory = null,
        public ?string $trafficSource = null,
        public ?int $affiliateId = null,
        public ?int $paymentGatewayId = null,
        public ?int $fulfillmentHouseId = null,
    ) {}

    /** @return array<string, mixed> */
    public function toQueryParams(): array
    {
        return array_filter([
            'df_from' => $this->quickRange === 'all_time' ? null : $this->from->toIso8601String(),
            'df_to' => $this->quickRange === 'all_time' ? null : $this->to->toIso8601String(),
            'df_range' => $this->quickRange === 'all_time' ? null : $this->quickRange,
            'df_currency' => $this->currency,
            'df_campaign' => $this->campaignId,
            'df_category' => $this->campaignCategory,
            'df_traffic' => $this->trafficSource,
            'df_affiliate' => $this->affiliateId,
            'df_gateway' => $this->paymentGatewayId,
            'df_fhouse' => $this->fulfillmentHouseId,
        ], fn (mixed $v): bool => $v !== null && $v !== '');
    }

    public function cacheKey(): string
    {
        return md5(json_encode($this->toQueryParams()));
    }
}
