<?php

namespace App\Modules\Subscription\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Events\SubscriptionCreated;
use App\Modules\Subscription\Events\SubscriptionCancelled;
use App\Modules\Subscription\Events\SubscriptionPaused;
use App\Modules\Subscription\Events\SubscriptionResumed;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

class SubscriptionLifecycleService
{
    public function __construct(
        private readonly BillingScheduleCalculator $scheduleCalculator,
    ) {}

    public function pause(Subscription $subscription): Subscription
    {
        if ($subscription->status === SubscriptionStatus::Paused) {
            return $subscription;
        }

        if (! in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue, SubscriptionStatus::Trial], true)) {
            throw new InvalidArgumentException('Only active subscriptions can be paused.');
        }

        $remainingDays = 0;

        if ($subscription->next_billing_at !== null && $subscription->next_billing_at->isFuture()) {
            $remainingDays = (int) now()->startOfDay()->diffInDays(
                $subscription->next_billing_at->copy()->startOfDay(),
            );
        }

        $subscription->paused_remaining_days = $remainingDays;
        $subscription->paused_at = now();
        $subscription->status = SubscriptionStatus::Paused;
        $subscription->save();

        Event::dispatch(new SubscriptionPaused($subscription));

        return $subscription->fresh();
    }

    public function resume(Subscription $subscription): Subscription
    {
        if ($subscription->status !== SubscriptionStatus::Paused) {
            throw new InvalidArgumentException('Subscription is not paused.');
        }

        $remainingDays = $subscription->paused_remaining_days ?? 0;
        $subscription->next_billing_at = now()->addDays($remainingDays);
        $subscription->paused_remaining_days = null;
        $subscription->paused_at = null;
        $subscription->status = SubscriptionStatus::Active;
        $subscription->save();

        Event::dispatch(new SubscriptionResumed($subscription));

        return $subscription->fresh();
    }

    public function cancel(Subscription $subscription, ?string $reason = null): Subscription
    {
        if ($subscription->status === SubscriptionStatus::Cancelled) {
            return $subscription;
        }

        $subscription->status = SubscriptionStatus::Cancelled;
        $subscription->cancelled_at = now();
        $subscription->ends_at = now();
        $subscription->cancellation_reason = $reason ?? 'user_cancelled';
        $subscription->save();

        Event::dispatch(new SubscriptionCancelled($subscription));

        return $subscription->fresh();
    }

    public function createFromPlan(
        int $customerId,
        SubscriptionPlan $plan,
        string $paymentToken,
        ?string $paymentProvider = null,
        array $attributes = [],
    ): Subscription {
        $firstStep = $plan->steps()->orderBy('step_order')->first();

        if ($firstStep === null) {
            throw new InvalidArgumentException('Subscription plan has no billing steps.');
        }

        $startedAt = now();
        $nextBilling = $this->scheduleCalculator->initialBillDate($firstStep, $startedAt);
        $cycleType = $firstStep->cycle_type;
        $isTrialCycle = $cycleType instanceof \App\Modules\Subscription\Enums\BillingCycleType
            ? $cycleType === \App\Modules\Subscription\Enums\BillingCycleType::Trial
            : (string) $cycleType === 'trial';
        $isTrial = (float) $firstStep->amount <= 0 || $isTrialCycle;

        $subscription = Subscription::query()->create(array_merge([
            'customer_id' => $customerId,
            'subscription_plan_id' => $plan->id,
            'current_cycle_step' => $firstStep->step_order,
            'status' => $isTrial ? SubscriptionStatus::Trial : SubscriptionStatus::Active,
            'currency' => $plan->currency,
            'amount' => $firstStep->amount,
            'billing_interval' => 'custom',
            'billing_interval_count' => 1,
            'payment_token' => $paymentToken,
            'payment_provider' => $paymentProvider,
            'started_at' => $startedAt,
            'next_billing_at' => $nextBilling,
            'trial_ends_at' => $isTrial ? $nextBilling : null,
            'current_period_starts_at' => $startedAt,
            'current_period_ends_at' => $nextBilling,
        ], $attributes));

        event(new SubscriptionCreated($subscription));

        return $subscription;
    }
}
