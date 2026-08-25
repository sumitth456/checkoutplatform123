<?php

namespace App\Modules\Subscription\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlanStep;
use InvalidArgumentException;

class CycleAmountResolver
{
    public function resolve(Subscription $subscription): array
    {
        $step = $this->currentStep($subscription);

        return [
            'amount' => number_format((float) $step->amount, 2, '.', ''),
            'currency' => strtoupper($step->currency ?? $subscription->currency),
            'step' => $step,
        ];
    }

    public function currentStep(Subscription $subscription): SubscriptionPlanStep
    {
        $plan = $subscription->plan;

        if ($plan === null) {
            throw new InvalidArgumentException('Subscription has no billing plan assigned.');
        }

        $step = $plan->steps->firstWhere('step_order', $subscription->current_cycle_step);

        if ($step === null) {
            throw new InvalidArgumentException("Plan step [{$subscription->current_cycle_step}] not found.");
        }

        return $step;
    }
}
