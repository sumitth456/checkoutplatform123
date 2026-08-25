<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class ShippingRulePolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'shipping_rules.view');
    }

    public function view(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'shipping_rules.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'shipping_rules.create');
    }

    public function update(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'shipping_rules.update');
    }

    public function delete(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'shipping_rules.delete');
    }

    public function restore(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'shipping_rules.delete');
    }

    public function forceDelete(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'shipping_rules.delete');
    }
}
