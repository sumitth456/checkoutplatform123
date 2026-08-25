<?php

namespace App\Modules\Ach\Services;

use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

final class AchAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function metrics(?string $from = null, ?string $to = null): array
    {
        $query = Transaction::query()
            ->where('payment_method_type', 'ach');

        if ($from !== null) {
            $query->where('created_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('created_at', '<=', $to);
        }

        $successful = (clone $query)->whereIn('status', ['captured', 'succeeded', 'completed', 'paid']);
        $failed = (clone $query)->whereIn('status', ['failed', 'declined']);
        $refunds = (clone $query)->where('type', 'refund');

        $achOrders = Order::query()->where('payment_method_type', 'ach');
        if ($from !== null) {
            $achOrders->where('created_at', '>=', $from);
        }
        if ($to !== null) {
            $achOrders->where('created_at', '<=', $to);
        }

        $totalAttempts = (clone $query)->where('type', 'capture')->count();
        $successCount = (clone $successful)->where('type', 'capture')->count();

        $rebillSuccess = DB::table('billing_ledger_entries')
            ->join('subscriptions', 'billing_ledger_entries.subscription_id', '=', 'subscriptions.id')
            ->where('subscriptions.payment_method_type', 'ach')
            ->where('billing_ledger_entries.outcome', 'success')
            ->when($from, fn ($q) => $q->where('billing_ledger_entries.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('billing_ledger_entries.created_at', '<=', $to))
            ->count();

        return [
            'ach_revenue' => (float) (clone $successful)->where('type', 'capture')->sum('amount'),
            'ach_orders' => (clone $achOrders)->count(),
            'ach_refunds' => (clone $refunds)->count(),
            'ach_refund_amount' => (float) (clone $refunds)->sum('amount'),
            'ach_success_rate' => $totalAttempts > 0 ? round(($successCount / $totalAttempts) * 100, 2) : 0,
            'ach_failed_payments' => (clone $failed)->count(),
            'ach_rebill_success' => $rebillSuccess,
        ];
    }
}
