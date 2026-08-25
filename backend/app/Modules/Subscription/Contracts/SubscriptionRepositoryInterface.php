<?php

namespace App\Modules\Subscription\Contracts;

use App\Models\Subscription;
use Illuminate\Support\Collection;

interface SubscriptionRepositoryInterface
{
    public function findDueForBilling(): Collection;

    public function findDueForDunning(): Collection;

    public function findById(int $subscriptionId): ?Subscription;

    public function lockForUpdate(int $subscriptionId): ?Subscription;
}
