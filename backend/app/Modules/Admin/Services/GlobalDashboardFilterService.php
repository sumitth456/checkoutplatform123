<?php

namespace App\Modules\Admin\Services;

use App\Models\Affiliate;
use App\Models\Campaign;
use App\Models\FulfillmentHouse;
use App\Models\Order;
use App\Models\PaymentGatewayConfig;
use App\Modules\Admin\Data\DashboardFilter;
use App\Modules\Admin\Support\DashboardSql;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class GlobalDashboardFilterService
{
    /** @return array<string, string> */
    public function quickRangeOptions(): array
    {
        return [
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'last_7_days' => 'Last 7 Days',
            'last_30_days' => 'Last 30 Days',
            'this_week' => 'This Week',
            'last_week' => 'Last Week',
            'this_month' => 'This Month',
            'last_month' => 'Last Month',
            'this_year' => 'This Year',
            'custom' => 'Custom Range',
        ];
    }

    /** @return array<string, string> */
    public function orderDateRangeOptions(): array
    {
        return [
            'all_time' => 'All Time',
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'last_7_days' => 'Last 7 Days',
            'last_30_days' => 'Last 30 Days',
            'this_month' => 'This Month',
            'last_month' => 'Last Month',
            'this_year' => 'This Year',
            'custom' => 'Custom Range',
        ];
    }

    public function resolve(
        ?string $quickRange = null,
        ?string $from = null,
        ?string $to = null,
        ?string $currency = null,
        ?int $campaignId = null,
        ?string $campaignCategory = null,
        ?string $trafficSource = null,
        ?int $affiliateId = null,
        ?int $paymentGatewayId = null,
        ?int $fulfillmentHouseId = null,
    ): DashboardFilter {
        $range = $quickRange ?: 'last_30_days';

        if ($range === 'all_time') {
            $fromDt = now()->subYears(10)->startOfDay();
            $toDt = now()->endOfDay();
        } elseif ($range === 'custom' && $from && $to) {
            $fromDt = Carbon::parse($from);
            $toDt = Carbon::parse($to);
        } else {
            [$fromDt, $toDt] = $this->resolveQuickRange($range);
        }

        $durationSeconds = max(1, $fromDt->diffInSeconds($toDt));
        $previousTo = $fromDt->copy()->subSecond();
        $previousFrom = $previousTo->copy()->subSeconds($durationSeconds);

        return new DashboardFilter(
            from: $fromDt,
            to: $toDt,
            previousFrom: $previousFrom,
            previousTo: $previousTo,
            quickRange: $range,
            currency: $currency ? strtoupper($currency) : 'USD',
            campaignId: $campaignId,
            campaignCategory: $campaignCategory ?: null,
            trafficSource: $trafficSource ?: null,
            affiliateId: $affiliateId,
            paymentGatewayId: $paymentGatewayId,
            fulfillmentHouseId: $fulfillmentHouseId,
        );
    }

    /** @return array{0: CarbonInterface, 1: CarbonInterface} */
    public function resolveQuickRange(string $range): array
    {
        return match ($range) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'last_7_days' => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
            'last_30_days' => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
            'this_week' => [now()->startOfWeek(), now()->endOfDay()],
            'last_week' => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
            'this_month' => [now()->startOfMonth(), now()->endOfDay()],
            'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'this_year' => [now()->startOfYear(), now()->endOfDay()],
            default => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
        };
    }

    /** @return Collection<int, Campaign> */
    public function campaignOptions(): Collection
    {
        return Campaign::query()->orderBy('name')->get(['id', 'name']);
    }

    /** @return Collection<int, string> */
    public function campaignCategoryOptions(): Collection
    {
        return Campaign::query()
            ->whereNotNull('metadata->category')
            ->selectRaw(DashboardSql::jsonText('metadata', 'category', 'category'))
            ->distinct()
            ->pluck('category')
            ->filter();
    }

    /** @return Collection<int, Affiliate> */
    public function affiliateOptions(): Collection
    {
        return Affiliate::query()->orderBy('name')->get(['id', 'name']);
    }

    /** @return Collection<int, PaymentGatewayConfig> */
    public function gatewayOptions(): Collection
    {
        return PaymentGatewayConfig::query()->orderBy('name')->get(['id', 'name', 'provider']);
    }

    /** @return Collection<int, FulfillmentHouse> */
    public function fulfillmentHouseOptions(): Collection
    {
        return FulfillmentHouse::query()->orderBy('name')->get(['id', 'name']);
    }

    /** @return Collection<int, string> */
    public function trafficSourceOptions(): Collection
    {
        return Order::query()
            ->whereNotNull('metadata->utm_source')
            ->selectRaw(DashboardSql::jsonText('metadata', 'utm_source', 'source'))
            ->distinct()
            ->pluck('source')
            ->filter();
    }

    /** @return Collection<int, string> */
    public function orderCurrencyOptions(): Collection
    {
        return Order::query()
            ->whereNotNull('currency')
            ->where('currency', '!=', '')
            ->distinct()
            ->orderBy('currency')
            ->pluck('currency');
    }

    /** @return Collection<int, string> */
    public function orderCountryOptions(): Collection
    {
        return Order::query()
            ->whereNotNull('country_code')
            ->where('country_code', '!=', '')
            ->distinct()
            ->orderBy('country_code')
            ->pluck('country_code');
    }

    /** @return Collection<int, string> */
    public function orderStateOptions(): Collection
    {
        $shippingStates = Order::query()
            ->whereNotNull('shipping_address')
            ->selectRaw(DashboardSql::jsonText('shipping_address', 'state', 'state_value'))
            ->distinct()
            ->pluck('state_value');

        $billingStates = Order::query()
            ->whereNotNull('billing_address')
            ->selectRaw(DashboardSql::jsonText('billing_address', 'state', 'state_value'))
            ->distinct()
            ->pluck('state_value');

        return $shippingStates
            ->merge($billingStates)
            ->filter(fn (?string $state): bool => filled($state))
            ->unique()
            ->sort()
            ->values();
    }
}
