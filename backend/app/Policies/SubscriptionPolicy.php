<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'subscriptions.view');
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return AdminAuthorization::can($user, 'subscriptions.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'subscriptions.create');
    }

    public function update(User $user, Subscription $subscription): bool
    {
        return AdminAuthorization::can($user, 'subscriptions.update');
    }

    public function delete(User $user, Subscription $subscription): bool
    {
        return AdminAuthorization::can($user, 'subscriptions.delete');
    }

    public function restore(User $user, Subscription $subscription): bool
    {
        return AdminAuthorization::can($user, 'subscriptions.delete');
    }

    public function forceDelete(User $user, Subscription $subscription): bool
    {
        return AdminAuthorization::can($user, 'subscriptions.delete');
    }

    public function export(User $user): bool
    {
        return AdminAuthorization::can($user, 'subscriptions.export');
    }

    public function manageBilling(User $user, Subscription $subscription): bool
    {
        return AdminAuthorization::can($user, 'subscriptions.billing');
    }
}
