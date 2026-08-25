<?php

namespace App\Modules\Subscription\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlanStep;
use App\Modules\Subscription\Enums\BillingStepType;
use App\Modules\Subscription\Enums\SubscriptionStatus;

class BillingCycleAdvancer
{
    public function __construct(
        private readonly BillingScheduleCalculator $scheduleCalculator,
    ) {}

    public function advanceAfterSuccess(Subscription $subscription, SubscriptionPlanStep $completedStep): void
    {
        $subscription->billing_cycle_count = (int) $subscription->billing_cycle_count + 1;

        if (in_array($subscription->status, [SubscriptionStatus::Trial, SubscriptionStatus::PastDue], true)) {
            $subscription->status = SubscriptionStatus::Active;
        }

        $plan = $subscription->relationLoaded('plan')
            ? $subscription->plan
            : $subscription->plan()->with('steps')->first();

        if ($completedStep->billing_type === BillingStepType::OneTime) {
            $nextStep = $plan?->steps->firstWhere('step_order', $completedStep->step_order + 1);

            if ($nextStep === null) {
                $subscription->next_billing_at = null;

                return;
            }

            $subscription->current_cycle_step = $nextStep->step_order;
            $scheduleStep = $nextStep;
        } else {
            $scheduleStep = $completedStep;
        }

        $subscription->amount = $scheduleStep->amount;
        $subscription->next_billing_at = $this->scheduleCalculator->nextBillDateAfterSuccess($scheduleStep);
        $subscription->current_period_starts_at = now();
        $subscription->current_period_ends_at = $subscription->next_billing_at;
    }
}
