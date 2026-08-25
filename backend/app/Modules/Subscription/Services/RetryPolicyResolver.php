<?php

namespace App\Modules\Subscription\Services;

use App\Models\RetryPolicy;
use App\Models\Subscription;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Events\SubscriptionExpired;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

class RetryPolicyResolver
{
    public function handleFailure(Subscription $subscription): void
    {
        $policy = $this->resolvePolicy($subscription);
        $subscription->retry_count++;

        if ($subscription->retry_count > $policy->max_attempts) {
            $this->expireSubscription($subscription);

            return;
        }

        $offsetDays = $this->offsetForAttempt($policy, $subscription->retry_count, $subscription);
        $subscription->status = SubscriptionStatus::PastDue;
        $subscription->next_billing_at = now()->addDays($offsetDays);
        $subscription->metadata = array_merge($subscription->metadata ?? [], [
            'last_rebill_retry_scheduled_at' => now()->toIso8601String(),
            'last_rebill_retry_in_days' => $offsetDays,
        ]);
        $subscription->save();
    }

    public function resetRetries(Subscription $subscription): void
    {
        $subscription->retry_count = 0;
    }

    private function expireSubscription(Subscription $subscription): void
    {
        $subscription->status = SubscriptionStatus::Cancelled;
        $subscription->cancelled_at = now();
        $subscription->ends_at = now();
        $subscription->cancellation_reason = 'retries_exhausted';
        $subscription->save();

        Event::dispatch(new SubscriptionExpired($subscription));
    }

    private function resolvePolicy(Subscription $subscription): RetryPolicy
    {
        $policy = $subscription->plan?->retryPolicy;

        if ($policy === null) {
            throw new InvalidArgumentException('Subscription plan has no retry policy configured.');
        }

        return $policy;
    }

    private function offsetForAttempt(RetryPolicy $policy, int $attempt, Subscription $subscription): int
    {
        if ($attempt === 1 && $this->isRebillCycle($subscription)) {
            return (int) config('subscription.rebill.first_retry_days', 7);
        }

        $offsets = $policy->retry_offsets;
        $index = $attempt - 1;

        if (! isset($offsets[$index])) {
            return (int) end($offsets);
        }

        return (int) $offsets[$index];
    }

    private function isRebillCycle(Subscription $subscription): bool
    {
        return (int) $subscription->billing_cycle_count > 0
            || (int) $subscription->current_cycle_step > 1;
    }
}
