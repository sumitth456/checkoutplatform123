<?php

namespace App\Modules\Admin\Services;

use App\Models\PaymentGatewayConfig;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class PaymentGatewayLoadService
{
    /**
     * @return array{
     *     daily_volume: float,
     *     monthly_volume: float,
     *     daily_limit: ?float,
     *     monthly_limit: ?float,
     *     within_limits: bool,
     *     success_rate_today: float,
     *     transaction_count_today: int
     * }
     */
    public function stats(PaymentGatewayConfig $gateway): array
    {
        $todayStart = now()->startOfDay();
        $monthStart = now()->startOfMonth();

        $dailyVolume = $this->volumeSince($gateway, $todayStart);
        $monthlyVolume = $this->volumeSince($gateway, $monthStart);

        $dailyLimit = $gateway->daily_limit_amount !== null ? (float) $gateway->daily_limit_amount : null;
        $monthlyLimit = $gateway->monthly_limit_amount !== null ? (float) $gateway->monthly_limit_amount : null;

        $successToday = Transaction::query()
            ->where('payment_gateway_id', $gateway->id)
            ->where('processed_at', '>=', $todayStart)
            ->whereIn('status', ['captured', 'succeeded', 'completed', 'paid'])
            ->count();

        $totalToday = Transaction::query()
            ->where('payment_gateway_id', $gateway->id)
            ->where('processed_at', '>=', $todayStart)
            ->count();

        return [
            'daily_volume' => $dailyVolume,
            'monthly_volume' => $monthlyVolume,
            'daily_limit' => $dailyLimit,
            'monthly_limit' => $monthlyLimit,
            'within_limits' => $this->isWithinLimits($gateway, $dailyVolume, $monthlyVolume),
            'success_rate_today' => $totalToday > 0 ? round(($successToday / $totalToday) * 100, 1) : 100.0,
            'transaction_count_today' => $totalToday,
        ];
    }

    public function isWithinLimits(PaymentGatewayConfig $gateway, ?float $dailyVolume = null, ?float $monthlyVolume = null): bool
    {
        $dailyVolume ??= $this->volumeSince($gateway, now()->startOfDay());
        $monthlyVolume ??= $this->volumeSince($gateway, now()->startOfMonth());

        if ($gateway->daily_limit_amount !== null && $dailyVolume >= (float) $gateway->daily_limit_amount) {
            return false;
        }

        if ($gateway->monthly_limit_amount !== null && $monthlyVolume >= (float) $gateway->monthly_limit_amount) {
            return false;
        }

        return true;
    }

    public function volumeSince(PaymentGatewayConfig $gateway, Carbon $since): float
    {
        return (float) Transaction::query()
            ->where('payment_gateway_id', $gateway->id)
            ->where('processed_at', '>=', $since)
            ->whereIn('status', ['captured', 'succeeded', 'completed', 'paid'])
            ->sum('amount');
    }
}
