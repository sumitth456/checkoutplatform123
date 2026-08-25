<?php

namespace App\Modules\Analytics\Repositories;

use App\Models\BillingLedgerEntry;
use App\Models\Order;
use App\Models\PaymentLedgerEntry;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Modules\Analytics\Contracts\AnalyticsRepositoryInterface;
use App\Modules\Analytics\Data\AnalyticsPeriod;
use App\Modules\Payment\Enums\LedgerEntryType;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Subscription\Enums\BillingLedgerOutcome;
use App\Modules\Subscription\Enums\BillingStepType;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use Illuminate\Support\Facades\DB;

class AnalyticsRepository implements AnalyticsRepositoryInterface
{
    public function revenueTotal(AnalyticsPeriod $period): array
    {
        $transactionQuery = Transaction::query()
            ->whereBetween('processed_at', [$period->from, $period->to])
            ->whereIn('status', ['captured', 'succeeded', 'completed', 'paid'])
            ->whereIn('type', ['capture', 'charge', 'sale', 'payment']);

        $this->applyCurrency($transactionQuery, $period, 'currency');

        $transactionTotal = (float) $transactionQuery->sum('amount');
        $transactionCount = (int) $transactionQuery->count();

        $rebillQuery = BillingLedgerEntry::query()
            ->whereBetween('attempted_at', [$period->from, $period->to])
            ->where('outcome', BillingLedgerOutcome::Success);

        if ($period->currency !== null) {
            $rebillQuery->where('currency', $period->currency);
        }

        $rebillTotal = (float) $rebillQuery->sum('amount');
        $rebillCount = (int) $rebillQuery->count();

        if ($transactionTotal > 0 || $rebillTotal > 0) {
            return [
                'total' => $transactionTotal + $rebillTotal,
                'count' => $transactionCount + $rebillCount,
                'currency' => $period->currency ?? 'USD',
            ];
        }

        $ledgerQuery = PaymentLedgerEntry::query()
            ->whereBetween('recorded_at', [$period->from, $period->to])
            ->where('entry_type', LedgerEntryType::Capture)
            ->where('status_to', PaymentStatus::Captured);

        if ($period->currency !== null) {
            $ledgerQuery->where('currency', $period->currency);
        }

        $ledgerTotal = (float) $ledgerQuery->sum('amount');
        $ledgerCount = (int) $ledgerQuery->count();

        if ($ledgerTotal > 0) {
            return [
                'total' => $ledgerTotal,
                'count' => $ledgerCount,
                'currency' => $period->currency ?? 'USD',
            ];
        }

        $orderQuery = Order::query()
            ->whereBetween('paid_at', [$period->from, $period->to])
            ->whereNotNull('paid_at');

        $this->applyCurrency($orderQuery, $period, 'currency');

        return [
            'total' => (float) $orderQuery->sum(DB::raw('grand_total - refunded_total')),
            'count' => (int) $orderQuery->count(),
            'currency' => $period->currency ?? 'USD',
        ];
    }

    public function refundTotal(AnalyticsPeriod $period): array
    {
        $transactionQuery = Transaction::query()
            ->whereBetween('processed_at', [$period->from, $period->to])
            ->where('type', 'refund')
            ->whereIn('status', ['refunded', 'succeeded', 'completed']);

        $this->applyCurrency($transactionQuery, $period, 'currency');

        $transactionTotal = (float) $transactionQuery->sum('amount');
        $transactionCount = (int) $transactionQuery->count();

        $ledgerQuery = PaymentLedgerEntry::query()
            ->whereBetween('recorded_at', [$period->from, $period->to])
            ->where('entry_type', LedgerEntryType::Refund);

        if ($period->currency !== null) {
            $ledgerQuery->where('currency', $period->currency);
        }

        $ledgerTotal = (float) $ledgerQuery->sum('amount');
        $ledgerCount = (int) $ledgerQuery->count();

        $orderQuery = Order::query()
            ->whereBetween('updated_at', [$period->from, $period->to])
            ->where('refunded_total', '>', 0);

        $this->applyCurrency($orderQuery, $period, 'currency');

        return [
            'total' => max($transactionTotal, $ledgerTotal, (float) $orderQuery->sum('refunded_total')),
            'count' => max($transactionCount, $ledgerCount, (int) $orderQuery->count()),
            'currency' => $period->currency ?? 'USD',
        ];
    }

    public function failedPaymentTotal(AnalyticsPeriod $period): array
    {
        $transactionQuery = Transaction::query()
            ->whereBetween('processed_at', [$period->from, $period->to])
            ->whereIn('status', ['failed', 'declined']);

        $this->applyCurrency($transactionQuery, $period, 'currency');

        $billingQuery = BillingLedgerEntry::query()
            ->whereBetween('attempted_at', [$period->from, $period->to])
            ->where('outcome', BillingLedgerOutcome::Failure);

        if ($period->currency !== null) {
            $billingQuery->where('currency', $period->currency);
        }

        $ledgerQuery = PaymentLedgerEntry::query()
            ->whereBetween('recorded_at', [$period->from, $period->to])
            ->where('status_to', PaymentStatus::Failed);

        if ($period->currency !== null) {
            $ledgerQuery->where('currency', $period->currency);
        }

        return [
            'failed_count' => (int) $transactionQuery->count() + (int) $ledgerQuery->count(),
            'failed_amount' => (float) $transactionQuery->sum('amount'),
            'billing_failure_count' => (int) $billingQuery->count(),
            'currency' => $period->currency ?? 'USD',
        ];
    }

    public function orderSummary(AnalyticsPeriod $period): array
    {
        $baseQuery = Order::query();
        $this->applyCurrency($baseQuery, $period, 'currency');

        $periodQuery = (clone $baseQuery)->whereBetween('placed_at', [$period->from, $period->to]);

        $byStatus = (clone $baseQuery)
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $byPaymentStatus = (clone $baseQuery)
            ->select('payment_status', DB::raw('count(*) as aggregate'))
            ->groupBy('payment_status')
            ->pluck('aggregate', 'payment_status')
            ->all();

        return [
            'total_orders' => (int) (clone $baseQuery)->count(),
            'new_orders' => (int) (clone $periodQuery)->count(),
            'gmv' => (float) (clone $periodQuery)->sum('grand_total'),
            'net_revenue' => (float) (clone $periodQuery)->sum(DB::raw('grand_total - refunded_total')),
            'by_status' => $byStatus,
            'by_payment_status' => $byPaymentStatus,
            'currency' => $period->currency ?? 'USD',
        ];
    }

    public function subscriptionSummary(AnalyticsPeriod $period): array
    {
        $baseQuery = Subscription::query();

        $byStatus = (clone $baseQuery)
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count, $status) => (int) $count)
            ->all();

        return [
            'total_subscriptions' => (int) (clone $baseQuery)->count(),
            'new_subscriptions' => (int) (clone $baseQuery)
                ->whereBetween('created_at', [$period->from, $period->to])
                ->count(),
            'cancelled_subscriptions' => (int) (clone $baseQuery)
                ->where('status', SubscriptionStatus::Cancelled)
                ->whereBetween('cancelled_at', [$period->from, $period->to])
                ->count(),
            'past_due_subscriptions' => (int) (clone $baseQuery)
                ->where('status', SubscriptionStatus::PastDue)
                ->count(),
            'by_status' => $byStatus,
        ];
    }

    public function activeSubscriptionBilling(AnalyticsPeriod $period): array
    {
        $subscriptions = Subscription::query()
            ->with(['plan.steps'])
            ->where('status', SubscriptionStatus::Active)
            ->when($period->currency !== null, fn ($query) => $query->where('currency', $period->currency))
            ->get();

        return $subscriptions->map(function (Subscription $subscription): array {
            $step = $subscription->plan?->steps
                ->firstWhere('step_order', $subscription->current_cycle_step);

            if ($step === null || $step->billing_type !== BillingStepType::Recurring) {
                $step = $subscription->plan?->steps
                    ->firstWhere('billing_type', BillingStepType::Recurring);
            }

            $amount = (float) ($step?->amount ?? $subscription->amount);
            $intervalDays = (int) ($step?->interval_days ?? 30);

            return [
                'amount' => $amount,
                'currency' => strtoupper((string) ($step?->currency ?? $subscription->currency)),
                'interval_days' => max(1, $intervalDays),
                'quantity' => (int) $subscription->quantity,
            ];
        })->all();
    }

    private function applyCurrency($query, AnalyticsPeriod $period, string $column): void
    {
        if ($period->currency !== null) {
            $query->where($column, $period->currency);
        }
    }
}
