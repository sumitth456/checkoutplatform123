<?php

namespace App\Modules\Admin\Services;

use App\Models\AffiliateClick;
use App\Models\AffiliateCommission;
use App\Models\AffiliateConversion;
use App\Models\AffiliatePayout;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\PaymentGatewayConfig;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Modules\Admin\Data\DashboardFilter;
use App\Modules\Admin\Support\DashboardSql;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Modules\Order\Enums\OrderFulfillmentStatus;
use App\Modules\Order\Enums\OrderPaymentStatus;
use App\Modules\Order\Enums\OrderType;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OperationalDashboardService
{
    public function __construct(
        private readonly DashboardFilterUrlBuilder $urls,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(DashboardFilter $filter): array
    {
        $snapshot = Cache::remember(
            'operational_dashboard_'.$filter->cacheKey(),
            60,
            fn (): array => $this->buildCacheableSnapshot($filter),
        );

        return [
            ...$snapshot,
            'filter' => $filter,
            'urls' => $this->urlHelpers($filter),
        ];
    }

    /** @return array<string, mixed> */
    private function buildCacheableSnapshot(DashboardFilter $filter): array
    {
        return [
            'kpis' => $this->kpiRow($filter),
            'sales' => $this->salesSummary($filter),
            'customer_service' => $this->customerServiceSummary($filter),
            'fulfillment' => $this->fulfillmentSummary($filter),
            'subscriptions' => $this->subscriptionSummary($filter),
            'affiliates' => $this->affiliateSummary($filter),
            'gateways' => $this->gatewaySummary($filter),
            'charts' => [
                'revenue' => $this->dailyTrend($filter, 'revenue'),
                'orders' => $this->dailyTrend($filter, 'orders'),
                'subscriptions' => $this->subscriptionGrowthTrend($filter),
                'rebills' => $this->rebillRevenueTrend($filter),
            ],
            'tops' => [
                'campaigns' => $this->topCampaigns($filter),
                'products' => $this->topProducts($filter),
                'affiliates' => $this->topAffiliates($filter),
                'gateways' => $this->topGateways($filter),
                'traffic_sources' => $this->topTrafficSources($filter),
                'countries' => $this->topGeo($filter, 'country_code'),
                'states' => $this->topGeo($filter, 'shipping_state'),
                'cities' => $this->topGeo($filter, 'shipping_city'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function urlHelpers(DashboardFilter $filter): array
    {
        return [
            'orders' => fn (array $extra = []) => $this->urls->orders($filter, $extra),
            'subscriptions' => fn (array $extra = []) => $this->urls->subscriptions($filter, $extra),
            'fulfillments' => fn (array $extra = []) => $this->urls->fulfillments($filter, $extra),
            'rebill_queue' => fn (array $extra = []) => $this->urls->rebillQueue($filter, $extra),
            'transactions' => fn (array $extra = []) => $this->urls->transactions($filter, $extra),
            'affiliates' => fn (array $extra = []) => $this->urls->affiliates($filter, $extra),
            'analytics' => $this->urls->analytics($filter),
        ];
    }

    /** @return array<string, array{value: mixed, change: float|null, sparkline: array<int, float>, url: string, format: string}> */
    private function kpiRow(DashboardFilter $filter): array
    {
        $orders = $this->orderCount($filter);
        $ordersPrev = $this->orderCount($this->previousFilter($filter));
        $gross = $this->grossRevenue($filter);
        $grossPrev = $this->grossRevenue($this->previousFilter($filter));
        $net = $this->netRevenue($filter);
        $netPrev = $this->netRevenue($this->previousFilter($filter));
        $rebill = $this->rebillRevenue($filter);
        $subRev = $this->subscriptionOrderRevenue($filter);
        $aov = $orders > 0 ? round($gross / $orders, 2) : 0.0;
        $aovPrev = $ordersPrev > 0 ? round($grossPrev / $ordersPrev, 2) : 0.0;
        $conv = $this->conversionRate($filter);
        $convPrev = $this->conversionRate($this->previousFilter($filter));
        $approval = $this->approvalRate($filter);
        $approvalPrev = $this->approvalRate($this->previousFilter($filter));

        $sparkOrders = array_column($this->dailyTrend($filter, 'orders'), 'value');
        $sparkRev = array_column($this->dailyTrend($filter, 'revenue'), 'value');

        return [
            'orders' => $this->metric($orders, $ordersPrev, $sparkOrders, $this->urls->orders($filter), 'number'),
            'gross_revenue' => $this->metric($gross, $grossPrev, $sparkRev, $this->urls->orders($filter, ['df_payment' => 'paid']), 'currency'),
            'net_revenue' => $this->metric($net, $netPrev, $sparkRev, $this->urls->orders($filter), 'currency'),
            'rebill_revenue' => $this->metric($rebill, $this->rebillRevenue($this->previousFilter($filter)), $sparkRev, $this->urls->orders($filter, ['tab' => 'rebill']), 'currency'),
            'subscription_revenue' => $this->metric($subRev, $this->subscriptionOrderRevenue($this->previousFilter($filter)), $sparkRev, $this->urls->subscriptions($filter), 'currency'),
            'aov' => $this->metric($aov, $aovPrev, $sparkRev, $this->urls->orders($filter), 'currency'),
            'conversion_rate' => $this->metric($conv, $convPrev, [], $this->urls->analytics($filter), 'percent'),
            'approval_rate' => $this->metric($approval, $approvalPrev, [], $this->urls->transactions($filter), 'percent'),
        ];
    }

    /** @return array<string, array{value: int|float, url: string}> */
    private function salesSummary(DashboardFilter $filter): array
    {
        $base = $this->orderQuery($filter);

        return [
            'total_orders' => ['value' => (clone $base)->count(), 'url' => $this->urls->orders($filter)],
            'approved' => ['value' => (clone $base)->where('payment_status', OrderPaymentStatus::Paid)->count(), 'url' => $this->urls->orders($filter, ['df_payment' => 'paid'])],
            'declined' => ['value' => (clone $base)->where('payment_status', OrderPaymentStatus::Failed)->count(), 'url' => $this->urls->orders($filter, ['df_payment' => 'failed'])],
            'pending' => ['value' => (clone $base)->where('payment_status', OrderPaymentStatus::Pending)->count(), 'url' => $this->urls->orders($filter, ['df_payment' => 'pending'])],
            'refunds' => ['value' => (clone $base)->whereIn('payment_status', [OrderPaymentStatus::Refunded, OrderPaymentStatus::PartiallyRefunded])->count(), 'url' => $this->urls->orders($filter, ['df_payment' => 'refunded'])],
            'chargebacks' => ['value' => $this->chargebackCount($filter), 'url' => $this->urls->transactions($filter, ['df_type' => 'chargeback'])],
            'upsells' => ['value' => (clone $base)->where('metadata->upsell', true)->count(), 'url' => $this->urls->orders($filter, ['df_upsell' => '1'])],
            'order_bumps' => ['value' => (clone $base)->where('metadata->order_bump', true)->count(), 'url' => $this->urls->orders($filter, ['df_bump' => '1'])],
            'subscription_orders' => ['value' => (clone $base)->whereNotNull('source_subscription_id')->where('order_type', OrderType::Initial)->count(), 'url' => $this->urls->orders($filter, ['df_subscription' => '1'])],
            'rebill_orders' => ['value' => (clone $base)->where('order_type', OrderType::Rebill)->count(), 'url' => $this->urls->orders($filter, ['tab' => 'rebill'])],
        ];
    }

    /** @return array<string, array{value: int, url: string}> */
    private function customerServiceSummary(DashboardFilter $filter): array
    {
        $refunds = $this->orderQuery($filter)->where('payment_status', OrderPaymentStatus::Refunded);

        return [
            'refund_requests' => ['value' => (clone $refunds)->count(), 'url' => $this->urls->orders($filter, ['df_payment' => 'refunded'])],
            'refund_completed' => ['value' => (clone $refunds)->where('refunded_total', '>', 0)->count(), 'url' => $this->urls->orders($filter, ['df_payment' => 'refunded'])],
            'partial_refunds' => ['value' => $this->orderQuery($filter)->where('payment_status', OrderPaymentStatus::PartiallyRefunded)->count(), 'url' => $this->urls->orders($filter, ['df_payment' => 'partially_refunded'])],
            'chargebacks' => ['value' => $this->chargebackCount($filter), 'url' => $this->urls->transactions($filter, ['df_type' => 'chargeback'])],
            'cancellations' => ['value' => $this->subscriptionQuery($filter)->where('status', SubscriptionStatus::Cancelled)->whereBetween('cancelled_at', [$filter->from, $filter->to])->count(), 'url' => $this->urls->subscriptions($filter, ['df_status' => 'cancelled'])],
            'tickets' => ['value' => 0, 'url' => '#'],
            'pending_tickets' => ['value' => 0, 'url' => '#'],
        ];
    }

    /** @return array<string, array{value: int, url: string}> */
    private function fulfillmentSummary(DashboardFilter $filter): array
    {
        $q = $this->fulfillmentQuery($filter);

        return [
            'pending' => ['value' => (clone $q)->whereIn('status', [FulfillmentRecordStatus::Pending, FulfillmentRecordStatus::Scheduled, FulfillmentRecordStatus::Queued])->count(), 'url' => $this->urls->fulfillments($filter, ['df_status' => 'pending'])],
            'sent' => ['value' => (clone $q)->where('status', FulfillmentRecordStatus::Submitted)->count(), 'url' => $this->urls->fulfillments($filter, ['df_status' => 'submitted'])],
            'packed' => ['value' => (clone $q)->where('status', FulfillmentRecordStatus::Packed)->count(), 'url' => $this->urls->fulfillments($filter, ['df_status' => 'packed'])],
            'shipped' => ['value' => (clone $q)->whereIn('status', [FulfillmentRecordStatus::Shipped, FulfillmentRecordStatus::InTransit])->count(), 'url' => $this->urls->fulfillments($filter, ['df_status' => 'shipped'])],
            'delivered' => ['value' => (clone $q)->where('status', FulfillmentRecordStatus::Delivered)->count(), 'url' => $this->urls->fulfillments($filter, ['df_status' => 'delivered'])],
            'returned' => ['value' => (clone $q)->where('status', FulfillmentRecordStatus::Returned)->count(), 'url' => $this->urls->fulfillments($filter, ['df_status' => 'returned'])],
            'cancelled' => ['value' => (clone $q)->where('status', FulfillmentRecordStatus::Cancelled)->count(), 'url' => $this->urls->fulfillments($filter, ['df_status' => 'cancelled'])],
            'failed' => ['value' => (clone $q)->where('status', FulfillmentRecordStatus::Failed)->count(), 'url' => $this->urls->fulfillments($filter, ['df_status' => 'failed'])],
            'rma_pending' => ['value' => (clone $q)->where('status', FulfillmentRecordStatus::RmaRequested)->count(), 'url' => $this->urls->fulfillments($filter, ['df_status' => 'rma_requested'])],
        ];
    }

    /** @return array<string, array{value: int, url: string}> */
    private function subscriptionSummary(DashboardFilter $filter): array
    {
        $q = $this->subscriptionQuery($filter);

        return [
            'active' => ['value' => Subscription::query()->where('status', SubscriptionStatus::Active)->when($filter->campaignId, fn ($b) => $b->where('platform_campaign_id', $filter->campaignId))->count(), 'url' => $this->urls->subscriptions($filter, ['df_status' => 'active'])],
            'paused' => ['value' => Subscription::query()->where('status', SubscriptionStatus::Paused)->count(), 'url' => $this->urls->subscriptions($filter, ['df_status' => 'paused'])],
            'cancelled' => ['value' => (clone $q)->where('status', SubscriptionStatus::Cancelled)->count(), 'url' => $this->urls->subscriptions($filter, ['df_status' => 'cancelled'])],
            'expired' => ['value' => 0, 'url' => $this->urls->subscriptions($filter, ['df_status' => 'expired'])],
            'upcoming_rebills' => ['value' => Subscription::query()->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])->whereBetween('next_billing_at', [now(), now()->addDays(7)])->count(), 'url' => $this->urls->rebillQueue($filter, ['df_upcoming' => '1'])],
            'successful_rebills' => ['value' => $this->orderQuery($filter)->where('order_type', OrderType::Rebill)->where('payment_status', OrderPaymentStatus::Paid)->count(), 'url' => $this->urls->orders($filter, ['tab' => 'rebill', 'df_payment' => 'paid'])],
            'failed_rebills' => ['value' => $this->orderQuery($filter)->where('order_type', OrderType::Rebill)->where('payment_status', OrderPaymentStatus::Failed)->count(), 'url' => $this->urls->rebillQueue($filter, ['df_failed' => '1'])],
            'retry_queue' => ['value' => Subscription::query()->where('status', SubscriptionStatus::PastDue)->count(), 'url' => $this->urls->rebillQueue($filter, ['df_retry' => '1'])],
        ];
    }

    /** @return array<string, array{value: int|float, url: string}> */
    private function affiliateSummary(DashboardFilter $filter): array
    {
        $clicks = AffiliateClick::query()->whereBetween('clicked_at', [$filter->from, $filter->to])
            ->when($filter->affiliateId, fn ($q) => $q->where('affiliate_id', $filter->affiliateId))
            ->when($filter->campaignId, fn ($q) => $q->where('platform_campaign_id', $filter->campaignId));
        $conversions = AffiliateConversion::query()->whereBetween('converted_at', [$filter->from, $filter->to])
            ->when($filter->affiliateId, fn ($q) => $q->where('affiliate_id', $filter->affiliateId))
            ->when($filter->campaignId, fn ($q) => $q->where('platform_campaign_id', $filter->campaignId));
        $clickCount = (clone $clicks)->count();
        $convCount = (clone $conversions)->count();
        $commission = (float) AffiliateCommission::query()->whereBetween('earned_at', [$filter->from, $filter->to])
            ->when($filter->affiliateId, fn ($q) => $q->where('affiliate_id', $filter->affiliateId))
            ->sum('commission_amount');
        $pendingPayout = (float) AffiliatePayout::query()->where('status', 'pending')
            ->when($filter->affiliateId, fn ($q) => $q->where('affiliate_id', $filter->affiliateId))
            ->sum('amount');
        $paid = (float) AffiliatePayout::query()->where('status', 'paid')->whereBetween('paid_at', [$filter->from, $filter->to])
            ->when($filter->affiliateId, fn ($q) => $q->where('affiliate_id', $filter->affiliateId))
            ->sum('amount');

        return [
            'clicks' => ['value' => $clickCount, 'url' => $this->urls->affiliates($filter)],
            'visitors' => ['value' => (clone $clicks)->where('is_unique', true)->count(), 'url' => $this->urls->affiliates($filter)],
            'conversions' => ['value' => $convCount, 'url' => $this->urls->affiliates($filter)],
            'conversion_rate' => ['value' => $clickCount > 0 ? round(($convCount / $clickCount) * 100, 1) : 0, 'url' => $this->urls->affiliates($filter)],
            'commission' => ['value' => $commission, 'url' => $this->urls->affiliates($filter)],
            'pending_payout' => ['value' => $pendingPayout, 'url' => $this->urls->affiliates($filter)],
            'paid_commission' => ['value' => $paid, 'url' => $this->urls->affiliates($filter)],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function gatewaySummary(DashboardFilter $filter): array
    {
        if (! Schema::hasTable('transactions')) {
            return [];
        }

        $rows = Transaction::query()
            ->select('payment_gateway_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status IN ('captured','succeeded','completed','paid') THEN 1 ELSE 0 END) as approved")
            ->selectRaw("SUM(CASE WHEN status IN ('failed','declined') THEN 1 ELSE 0 END) as declined")
            ->whereBetween('processed_at', [$filter->from, $filter->to])
            ->when($filter->paymentGatewayId, fn ($q) => $q->where('payment_gateway_id', $filter->paymentGatewayId))
            ->when($filter->currency, fn ($q) => $q->where('currency', $filter->currency))
            ->whereNotNull('payment_gateway_id')
            ->groupBy('payment_gateway_id')
            ->get();

        $names = PaymentGatewayConfig::query()->whereIn('id', $rows->pluck('payment_gateway_id'))->pluck('name', 'id');

        return $rows->map(function ($row) use ($names, $filter): array {
            $total = (int) $row->total;
            $approved = (int) $row->approved;

            return [
                'name' => (string) ($names[$row->payment_gateway_id] ?? 'Gateway #'.$row->payment_gateway_id),
                'transactions' => $total,
                'approved' => $approved,
                'declined' => (int) $row->declined,
                'approval_rate' => $total > 0 ? round(($approved / $total) * 100, 1) : 0,
                'url' => $this->urls->transactions($filter, ['df_gateway' => $row->payment_gateway_id]),
            ];
        })->sortByDesc('transactions')->values()->all();
    }

    /** @return array<int, array{label: string, value: float}> */
    private function dailyTrend(DashboardFilter $filter, string $metric): array
    {
        if (! Schema::hasTable('orders')) {
            return [];
        }

        $column = $metric === 'revenue' ? 'SUM(grand_total - refunded_total)' : 'COUNT(*)';
        $rows = $this->orderQuery($filter)
            ->whereNotNull('paid_at')
            ->selectRaw("DATE(paid_at) as day, {$column} as val")
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('val', 'day');

        $trend = [];
        $cursor = $filter->from->copy()->startOfDay();
        while ($cursor->lte($filter->to)) {
            $key = $cursor->toDateString();
            $trend[] = ['label' => $cursor->format('M j'), 'value' => (float) ($rows[$key] ?? 0)];
            $cursor->addDay();
        }

        return $trend;
    }

    /** @return array<int, array{label: string, value: int}> */
    private function subscriptionGrowthTrend(DashboardFilter $filter): array
    {
        $rows = Subscription::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as val')
            ->whereBetween('created_at', [$filter->from, $filter->to])
            ->when($filter->campaignId, fn ($q) => $q->where('platform_campaign_id', $filter->campaignId))
            ->groupBy('day')
            ->pluck('val', 'day');

        $trend = [];
        $cursor = $filter->from->copy()->startOfDay();
        while ($cursor->lte($filter->to)) {
            $key = $cursor->toDateString();
            $trend[] = ['label' => $cursor->format('M j'), 'value' => (int) ($rows[$key] ?? 0)];
            $cursor->addDay();
        }

        return $trend;
    }

    /** @return array<int, array{label: string, value: float}> */
    private function rebillRevenueTrend(DashboardFilter $filter): array
    {
        $rows = $this->orderQuery($filter)
            ->where('order_type', OrderType::Rebill)
            ->whereNotNull('paid_at')
            ->selectRaw('DATE(paid_at) as day, SUM(grand_total - refunded_total) as val')
            ->groupBy('day')
            ->pluck('val', 'day');

        $trend = [];
        $cursor = $filter->from->copy()->startOfDay();
        while ($cursor->lte($filter->to)) {
            $key = $cursor->toDateString();
            $trend[] = ['label' => $cursor->format('M j'), 'value' => (float) ($rows[$key] ?? 0)];
            $cursor->addDay();
        }

        return $trend;
    }

    /** @return array<int, array{name: string, value: mixed, url: string}> */
    private function topCampaigns(DashboardFilter $filter): array
    {
        return $this->orderQuery($filter)
            ->whereNotNull('platform_campaign_id')
            ->select('platform_campaign_id')
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('SUM(grand_total - refunded_total) as revenue')
            ->groupBy('platform_campaign_id')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'name' => DB::table('campaigns')->where('id', $row->platform_campaign_id)->value('name') ?? 'Campaign #'.$row->platform_campaign_id,
                'value' => (float) $row->revenue,
                'orders' => (int) $row->orders,
                'url' => $this->urls->orders($filter, ['df_campaign' => $row->platform_campaign_id]),
            ])->all();
    }

    /** @return array<int, array{name: string, value: float, url: string}> */
    private function topProducts(DashboardFilter $filter): array
    {
        if (! Schema::hasTable('order_items')) {
            return [];
        }

        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.paid_at', [$filter->from, $filter->to])
            ->when($filter->campaignId, fn ($q) => $q->where('orders.platform_campaign_id', $filter->campaignId))
            ->select('order_items.product_id', 'order_items.name')
            ->selectRaw('SUM(order_items.total) as revenue')
            ->groupBy('order_items.product_id', 'order_items.name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'name' => (string) $row->name,
                'value' => (float) $row->revenue,
                'url' => $row->product_id ? $this->urls->product($filter, (int) $row->product_id) : $this->urls->orders($filter),
            ])->all();
    }

    /** @return array<int, array{name: string, value: float, url: string}> */
    private function topAffiliates(DashboardFilter $filter): array
    {
        return AffiliateConversion::query()
            ->whereBetween('converted_at', [$filter->from, $filter->to])
            ->select('affiliate_id')
            ->selectRaw('SUM(revenue) as revenue')
            ->selectRaw('COUNT(*) as conversions')
            ->groupBy('affiliate_id')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'name' => DB::table('affiliates')->where('id', $row->affiliate_id)->value('name') ?? 'Affiliate #'.$row->affiliate_id,
                'value' => (float) $row->revenue,
                'url' => $this->urls->affiliates($filter, ['df_affiliate' => $row->affiliate_id]),
            ])->all();
    }

    /** @return array<int, array{name: string, value: int, url: string}> */
    private function topGateways(DashboardFilter $filter): array
    {
        return collect($this->gatewaySummary($filter))
            ->take(5)
            ->map(fn ($g) => ['name' => $g['name'], 'value' => $g['transactions'], 'url' => $g['url']])
            ->all();
    }

    /** @return array<int, array{name: string, value: int, url: string}> */
    private function topTrafficSources(DashboardFilter $filter): array
    {
        return $this->orderQuery($filter)
            ->whereNotNull('metadata->utm_source')
            ->selectRaw(DashboardSql::jsonText('metadata', 'utm_source', 'source'))
            ->selectRaw('COUNT(*) as orders')
            ->groupBy('source')
            ->orderByDesc('orders')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'name' => (string) ($row->source ?: 'Direct'),
                'value' => (int) $row->orders,
                'url' => $this->urls->orders($filter, ['df_traffic' => $row->source]),
            ])->all();
    }

    /** @return array<int, array{name: string, value: int, url: string}> */
    private function topGeo(DashboardFilter $filter, string $column): array
    {
        $isCountry = $column === 'country_code';
        $isCity = $column === 'shipping_city';

        $select = match (true) {
            $isCountry => 'country_code as geo',
            $isCity => DashboardSql::jsonText('shipping_address', 'city', 'geo'),
            default => DashboardSql::jsonText('shipping_address', 'state', 'geo'),
        };

        return $this->orderQuery($filter)
            ->when(! $isCountry, fn ($q) => $q->whereNotNull('shipping_address'))
            ->when($isCountry, fn ($q) => $q->whereNotNull('country_code'))
            ->selectRaw("{$select}")
            ->selectRaw('COUNT(*) as orders')
            ->groupBy('geo')
            ->orderByDesc('orders')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'name' => (string) ($row->geo ?: 'Unknown'),
                'value' => (int) $row->orders,
                'url' => $this->urls->orders($filter),
            ])->all();
    }

    private function orderQuery(DashboardFilter $filter): Builder
    {
        return Order::query()
            ->whereBetween('placed_at', [$filter->from, $filter->to])
            ->when($filter->currency, fn ($q) => $q->where('currency', $filter->currency))
            ->when($filter->campaignId, fn ($q) => $q->where('platform_campaign_id', $filter->campaignId))
            ->when($filter->affiliateId, fn ($q) => $q->where('affiliate_id', $filter->affiliateId))
            ->when($filter->paymentGatewayId, fn ($q) => $q->where('payment_gateway_id', $filter->paymentGatewayId))
            ->when($filter->trafficSource, fn ($q) => $q->where('metadata->utm_source', $filter->trafficSource))
            ->when($filter->campaignCategory, fn ($q) => $q->whereHas('platformCampaign', fn ($c) => $c->where('metadata->category', $filter->campaignCategory)));
    }

    private function fulfillmentQuery(DashboardFilter $filter): Builder
    {
        return Fulfillment::query()
            ->whereBetween('created_at', [$filter->from, $filter->to])
            ->when($filter->fulfillmentHouseId, fn ($q) => $q->where('fulfillment_house_id', $filter->fulfillmentHouseId))
            ->when($filter->campaignId || $filter->affiliateId, function ($q) use ($filter): void {
                $q->whereHas('order', function ($oq) use ($filter): void {
                    if ($filter->campaignId) {
                        $oq->where('platform_campaign_id', $filter->campaignId);
                    }
                    if ($filter->affiliateId) {
                        $oq->where('affiliate_id', $filter->affiliateId);
                    }
                });
            });
    }

    private function subscriptionQuery(DashboardFilter $filter): Builder
    {
        return Subscription::query()
            ->whereBetween('created_at', [$filter->from, $filter->to])
            ->when($filter->campaignId, fn ($q) => $q->where('platform_campaign_id', $filter->campaignId));
    }

    private function orderCount(DashboardFilter $filter): int
    {
        return $this->orderQuery($filter)->count();
    }

    private function grossRevenue(DashboardFilter $filter): float
    {
        return (float) $this->orderQuery($filter)->where('payment_status', OrderPaymentStatus::Paid)->sum('grand_total');
    }

    private function netRevenue(DashboardFilter $filter): float
    {
        return (float) $this->orderQuery($filter)->where('payment_status', OrderPaymentStatus::Paid)
            ->sum(DB::raw('grand_total - refunded_total'));
    }

    private function rebillRevenue(DashboardFilter $filter): float
    {
        return (float) $this->orderQuery($filter)->where('order_type', OrderType::Rebill)
            ->where('payment_status', OrderPaymentStatus::Paid)
            ->sum(DB::raw('grand_total - refunded_total'));
    }

    private function subscriptionOrderRevenue(DashboardFilter $filter): float
    {
        return (float) $this->orderQuery($filter)->whereNotNull('source_subscription_id')
            ->where('payment_status', OrderPaymentStatus::Paid)
            ->sum(DB::raw('grand_total - refunded_total'));
    }

    private function chargebackCount(DashboardFilter $filter): int
    {
        if (! Schema::hasTable('transactions')) {
            return 0;
        }

        return (int) Transaction::query()
            ->whereBetween('processed_at', [$filter->from, $filter->to])
            ->whereIn('type', ['chargeback', 'dispute'])
            ->when($filter->paymentGatewayId, fn ($q) => $q->where('payment_gateway_id', $filter->paymentGatewayId))
            ->count();
    }

    private function conversionRate(DashboardFilter $filter): float
    {
        $clicks = AffiliateClick::query()->whereBetween('clicked_at', [$filter->from, $filter->to])->count();
        $orders = $this->orderCount($filter);

        return $clicks > 0 ? round(($orders / $clicks) * 100, 1) : ($orders > 0 ? 100.0 : 0.0);
    }

    private function approvalRate(DashboardFilter $filter): float
    {
        if (! Schema::hasTable('transactions')) {
            $total = $this->orderCount($filter);
            $paid = $this->orderQuery($filter)->where('payment_status', OrderPaymentStatus::Paid)->count();

            return $total > 0 ? round(($paid / $total) * 100, 1) : 0.0;
        }

        $total = Transaction::query()->whereBetween('processed_at', [$filter->from, $filter->to])->count();
        $approved = Transaction::query()->whereBetween('processed_at', [$filter->from, $filter->to])
            ->whereIn('status', ['captured', 'succeeded', 'completed', 'paid'])->count();

        return $total > 0 ? round(($approved / $total) * 100, 1) : 0.0;
    }

    private function previousFilter(DashboardFilter $filter): DashboardFilter
    {
        return new DashboardFilter(
            from: $filter->previousFrom,
            to: $filter->previousTo,
            previousFrom: $filter->previousFrom->copy()->subSeconds(max(1, $filter->previousFrom->diffInSeconds($filter->previousTo))),
            previousTo: $filter->previousFrom->copy()->subSecond(),
            quickRange: $filter->quickRange,
            currency: $filter->currency,
            campaignId: $filter->campaignId,
            campaignCategory: $filter->campaignCategory,
            trafficSource: $filter->trafficSource,
            affiliateId: $filter->affiliateId,
            paymentGatewayId: $filter->paymentGatewayId,
            fulfillmentHouseId: $filter->fulfillmentHouseId,
        );
    }

    /**
     * @param  array<int, float>  $sparkline
     * @return array{value: mixed, change: float|null, sparkline: array<int, float>, url: string, format: string}
     */
    private function metric(mixed $value, mixed $previous, array $sparkline, string $url, string $format): array
    {
        $change = null;
        if (is_numeric($previous) && (float) $previous != 0.0) {
            $change = round((((float) $value - (float) $previous) / (float) $previous) * 100, 1);
        }

        return [
            'value' => $value,
            'change' => $change,
            'sparkline' => $sparkline,
            'url' => $url,
            'format' => $format,
        ];
    }
}
