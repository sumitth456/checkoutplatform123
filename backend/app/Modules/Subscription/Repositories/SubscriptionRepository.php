<?php

namespace App\Modules\Subscription\Repositories;

use App\Models\Subscription;
use App\Modules\Subscription\Contracts\SubscriptionRepositoryInterface;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use Illuminate\Support\Collection;

class SubscriptionRepository implements SubscriptionRepositoryInterface
{
    public function findDueForBilling(): Collection
    {
        return Subscription::query()
            ->with(['plan.steps', 'plan.retryPolicy', 'plan.dunningPolicy'])
            ->whereIn('status', [
                SubscriptionStatus::Trial,
                SubscriptionStatus::Active,
                SubscriptionStatus::PastDue,
            ])
            ->whereNotNull('next_billing_at')
            ->where('next_billing_at', '<=', now())
            ->get();
    }

    public function findDueForDunning(): Collection
    {
        return Subscription::query()
            ->with(['plan.dunningPolicy'])
            ->whereIn('status', [
                SubscriptionStatus::PastDue,
                SubscriptionStatus::Active,
                SubscriptionStatus::Trial,
            ])
            ->whereNotNull('next_dunning_at')
            ->where('next_dunning_at', '<=', now())
            ->get();
    }

    public function findById(int $subscriptionId): ?Subscription
    {
        return Subscription::query()
            ->with(['plan.steps', 'plan.retryPolicy', 'plan.dunningPolicy'])
            ->find($subscriptionId);
    }

    public function lockForUpdate(int $subscriptionId): ?Subscription
    {
        return Subscription::query()
            ->with(['plan.steps', 'plan.retryPolicy', 'plan.dunningPolicy'])
            ->whereKey($subscriptionId)
            ->lockForUpdate()
            ->first();
    }
}
