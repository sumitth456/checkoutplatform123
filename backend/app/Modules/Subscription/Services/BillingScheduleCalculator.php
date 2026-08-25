<?php

namespace App\Modules\Subscription\Services;

use App\Models\SubscriptionPlanStep;
use App\Modules\Subscription\Enums\BillingCycleType;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class BillingScheduleCalculator
{
    public function initialBillDate(SubscriptionPlanStep $step, ?CarbonInterface $startedAt = null): Carbon
    {
        $from = Carbon::instance($startedAt ?? now());

        if ($this->cycleType($step) === BillingCycleType::Trial) {
            return $from->copy()->addDays((int) ($step->days_from_start ?? 0));
        }

        if ($step->days_from_start !== null && (int) $step->days_from_start > 0) {
            return $from->copy()->addDays((int) $step->days_from_start);
        }

        return $this->nextBillDateAfterSuccess($step, $from);
    }

    public function nextBillDateAfterSuccess(SubscriptionPlanStep $step, ?CarbonInterface $from = null): Carbon
    {
        $from = Carbon::instance($from ?? now());

        return match ($this->cycleType($step)) {
            BillingCycleType::Day7Rebill => $from->copy()->addDays(7),
            BillingCycleType::Day30Rebill => $from->copy()->addDays(30),
            BillingCycleType::Weekly => $from->copy()->addWeek(),
            BillingCycleType::Monthly => $from->copy()->addMonth(),
            BillingCycleType::Trial => $from->copy()->addDays((int) ($step->days_from_start ?? 0)),
            BillingCycleType::Custom => $from->copy()->addDays((int) ($step->interval_days ?? 30)),
        };
    }

    private function cycleType(SubscriptionPlanStep $step): BillingCycleType
    {
        $raw = $step->cycle_type;

        if ($raw instanceof BillingCycleType) {
            return $raw;
        }

        return BillingCycleType::tryFrom((string) $raw) ?? BillingCycleType::Custom;
    }
}
