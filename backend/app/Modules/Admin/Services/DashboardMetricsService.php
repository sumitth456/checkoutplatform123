<?php

namespace App\Modules\Admin\Services;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentGatewayConfig;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Modules\Admin\Data\DashboardMetrics;
use App\Modules\Analytics\Contracts\AnalyticsRepositoryInterface;
use App\Modules\Analytics\Data\AnalyticsPeriod;
use App\Modules\Analytics\Services\AnalyticsService;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Modules\Order\Enums\OrderFulfillmentStatus;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardMetricsService
{
    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly AnalyticsRepositoryInterface $analyticsRepository,
    ) {}

    public function metrics(?string $currency = null): DashboardMetrics
    {
        $currency = $currency ? strtoupper($currency) : 'USD';
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfDay();
        $trendStart = now()->subDays(29)->startOfDay();

        $todayPeriod = new AnalyticsPeriod($todayStart, $todayEnd, $currency);
        $monthPeriod = new AnalyticsPeriod($monthStart, $monthEnd, $currency);

        $todayOrders = $this->countOrdersBetween($todayStart, $todayEnd, $currency);
        $todayRevenue = (float) $this->analyticsRepository->revenueTotal($todayPeriod)['total'];
        $monthlyRevenue = (float) $this->analyticsRepository->revenueTotal($monthPeriod)['total'];

        $activeSubscriptions = Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->when($currency, fn ($q) => $q->where('currency', $currency))
            ->count();

        $pendingRebill = Subscription::query()
            ->whereIn('status', [
                SubscriptionStatus::Active,
                SubscriptionStatus::PastDue,
                SubscriptionStatus::Trial,
            ])
            ->whereNotNull('next_billing_at')
            ->where('next_billing_at', '<=', now())
            ->when($currency, fn ($q) => $q->where('currency', $currency))
            ->count();

        $failedPaymentsToday = (int) $this->analytics->failedPayments($todayPeriod)->failedCount
            + (int) $this->analytics->failedPayments($todayPeriod)->billingFailureCount;

        $pendingFulfillment = Order::query()
            ->whereIn('fulfillment_status', [
                OrderFulfillmentStatus::Unfulfilled,
                OrderFulfillmentStatus::Scheduled,
                OrderFulfillmentStatus::Processing,
            ])
            ->when($currency, fn ($q) => $q->where('currency', $currency))
            ->count();

        $delayedShipments = Fulfillment::query()
            ->where('status', FulfillmentRecordStatus::Scheduled)
            ->whereDate('scheduled_ship_date', '<', now()->toDateString())
            ->count();

        $refundDataToday = $this->analyticsRepository->refundTotal($todayPeriod);

        $chargebackQuery = Transaction::query()
            ->whereBetween('processed_at', [$monthStart, $monthEnd])
            ->whereIn('type', ['chargeback', 'dispute'])
            ->when($currency, fn ($q) => $q->where('currency', $currency));

        $gatewaySuccessRate = $this->gatewaySuccessRate($monthStart, $monthEnd, $currency);

        return new DashboardMetrics(
            todayOrders: $todayOrders,
            todayRevenue: $todayRevenue,
            monthlyRevenue: $monthlyRevenue,
            activeSubscriptions: $activeSubscriptions,
            pendingRebill: $pendingRebill,
            failedPaymentsToday: $failedPaymentsToday,
            pendingFulfillment: $pendingFulfillment,
            delayedShipments: $delayedShipments,
            refundsToday: (int) $refundDataToday['count'],
            refundsAmountToday: (float) $refundDataToday['total'],
            chargebacksPeriod: (int) (clone $chargebackQuery)->count(),
            chargebacksAmountPeriod: (float) (clone $chargebackQuery)->sum('amount'),
            gatewaySuccessRate: $gatewaySuccessRate,
            currency: $currency,
            revenueTrend: $this->revenueTrend($trendStart, $todayEnd, $currency),
            gatewayHealth: $this->gatewayHealth($monthStart, $monthEnd, $currency),
            topProducts: $this->topProducts($monthStart, $monthEnd, $currency),
            topFunnels: $this->topFunnels($monthStart, $monthEnd, $currency),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function periodSnapshot(AnalyticsPeriod $period): array
    {
        $currency = strtoupper($period->currency ?? 'USD');
        $revenueData = $this->analyticsRepository->revenueTotal($period);
        $refundData = $this->analyticsRepository->refundTotal($period);
        $failedData = $this->analyticsRepository->failedPaymentTotal($period);
        $orderData = $this->analyticsRepository->orderSummary($period);
        $subscriptionData = $this->analyticsRepository->subscriptionSummary($period);

        $mrr = 0.0;
        foreach ($this->analyticsRepository->activeSubscriptionBilling($period) as $billing) {
            $intervalDays = max(1, (int) ($billing['interval_days'] ?? 30));
            $mrr += $billing['amount'] * (30 / $intervalDays) * $billing['quantity'];
        }

        return [
            'currency' => $currency,
            'revenue_total' => (float) $revenueData['total'],
            'revenue_count' => (int) $revenueData['count'],
            'refund_total' => (float) $refundData['total'],
            'refund_count' => (int) $refundData['count'],
            'failed_checkout_count' => (int) $failedData['failed_count'],
            'failed_billing_count' => (int) $failedData['billing_failure_count'],
            'orders_total' => (int) $orderData['new_orders'],
            'orders_gmv' => (float) $orderData['gmv'],
            'orders_net_revenue' => (float) $orderData['net_revenue'],
            'new_subscriptions' => (int) $subscriptionData['new_subscriptions'],
            'cancelled_subscriptions' => (int) $subscriptionData['cancelled_subscriptions'],
            'past_due_subscriptions' => (int) $subscriptionData['past_due_subscriptions'],
            'mrr' => round($mrr, 2),
            'arr' => round($mrr * 12, 2),
            'gateway_success_rate' => $this->gatewaySuccessRate($period->from, $period->to, $currency),
            'revenue_trend' => $this->revenueTrend($period->from, $period->to, $currency),
            'gateway_health' => $this->gatewayHealth($period->from, $period->to, $currency),
            'top_products' => $this->topProducts($period->from, $period->to, $currency),
            'top_funnels' => $this->topFunnels($period->from, $period->to, $currency),
        ];
    }

    private function countOrdersBetween(Carbon $from, Carbon $to, string $currency): int
    {
        if (! Schema::hasTable('orders')) {
            return 0;
        }

        return (int) Order::query()
            ->whereBetween('placed_at', [$from, $to])
            ->when($currency, fn ($q) => $q->where('currency', $currency))
            ->count();
    }

    /**
     * @return array<int, array{label: string, revenue: float, orders: int}>
     */
    private function revenueTrend(Carbon $from, Carbon $to, string $currency): array
    {
        if (! Schema::hasTable('orders')) {
            return [];
        }

        $rows = Order::query()
            ->selectRaw('DATE(paid_at) as day, COUNT(*) as orders, SUM(grand_total - refunded_total) as revenue')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from, $to])
            ->when($currency, fn ($q) => $q->where('currency', $currency))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $indexed = $rows->keyBy('day');
        $trend = [];
        $cursor = $from->copy()->startOfDay();

        while ($cursor->lte($to)) {
            $key = $cursor->toDateString();
            $row = $indexed->get($key);
            $trend[] = [
                'label' => $cursor->format('M j'),
                'revenue' => (float) ($row->revenue ?? 0),
                'orders' => (int) ($row->orders ?? 0),
            ];
            $cursor->addDay();
        }

        return $trend;
    }

    /**
     * @return array<int, array{name: string, success_rate: float, success: int, failed: int}>
     */
    private function gatewayHealth(Carbon $from, Carbon $to, string $currency): array
    {
        if (! Schema::hasTable('transactions')) {
            return [];
        }

        $aggregates = Transaction::query()
            ->select('payment_gateway_id')
            ->selectRaw(
                'SUM(CASE WHEN status IN (?, ?, ?, ?) THEN 1 ELSE 0 END) as success_count',
                ['captured', 'succeeded', 'completed', 'paid'],
            )
            ->selectRaw(
                'SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as failed_count',
                ['failed', 'declined'],
            )
            ->whereBetween('processed_at', [$from, $to])
            ->when($currency, fn ($q) => $q->where('currency', $currency))
            ->whereNotNull('payment_gateway_id')
            ->groupBy('payment_gateway_id')
            ->get();

        $gatewayNames = PaymentGatewayConfig::query()
            ->whereIn('id', $aggregates->pluck('payment_gateway_id'))
            ->pluck('name', 'id');

        return $aggregates->map(function ($row) use ($gatewayNames): array {
            $success = (int) $row->success_count;
            $failed = (int) $row->failed_count;
            $total = $success + $failed;
            $rate = $total > 0 ? round(($success / $total) * 100, 1) : 100.0;

            return [
                'name' => (string) ($gatewayNames[$row->payment_gateway_id] ?? 'Gateway #'.$row->payment_gateway_id),
                'success_rate' => $rate,
                'success' => $success,
                'failed' => $failed,
            ];
        })->sortByDesc('success')->values()->all();
    }

    private function gatewaySuccessRate(Carbon $from, Carbon $to, string $currency): float
    {
        $health = $this->gatewayHealth($from, $to, $currency);

        if ($health === []) {
            return 100.0;
        }

        $success = array_sum(array_column($health, 'success'));
        $failed = array_sum(array_column($health, 'failed'));
        $total = $success + $failed;

        return $total > 0 ? round(($success / $total) * 100, 1) : 100.0;
    }

    /**
     * @return array<int, array{name: string, orders: int, revenue: float}>
     */
    private function topProducts(Carbon $from, Carbon $to, string $currency): array
    {
        if (! Schema::hasTable('order_items')) {
            return [];
        }

        return OrderItem::query()
            ->select('order_items.name')
            ->selectRaw('COUNT(DISTINCT order_items.order_id) as orders')
            ->selectRaw('SUM(order_items.total) as revenue')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotNull('orders.paid_at')
            ->whereBetween('orders.paid_at', [$from, $to])
            ->when($currency, fn ($q) => $q->where('orders.currency', $currency))
            ->groupBy('order_items.name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'name' => (string) $row->name,
                'orders' => (int) $row->orders,
                'revenue' => (float) $row->revenue,
            ])
            ->all();
    }

    /**
     * @return array<int, array{name: string, orders: int, revenue: float}>
     */
    private function topFunnels(Carbon $from, Carbon $to, string $currency): array
    {
        if (! Schema::hasTable('orders')) {
            return [];
        }

        return Order::query()
            ->select('funnel_id')
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('SUM(grand_total - refunded_total) as revenue')
            ->whereNotNull('paid_at')
            ->whereNotNull('funnel_id')
            ->whereBetween('paid_at', [$from, $to])
            ->when($currency, fn ($q) => $q->where('currency', $currency))
            ->groupBy('funnel_id')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(function ($row): array {
                $name = DB::table('funnels')->where('id', $row->funnel_id)->value('name');

                return [
                    'name' => (string) ($name ?? 'Funnel #'.$row->funnel_id),
                    'orders' => (int) $row->orders,
                    'revenue' => (float) $row->revenue,
                ];
            })
            ->all();
    }
}
