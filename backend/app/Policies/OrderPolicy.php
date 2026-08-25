<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'orders.view');
    }

    public function view(User $user, Order $order): bool
    {
        return AdminAuthorization::can($user, 'orders.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'orders.create');
    }

    public function update(User $user, Order $order): bool
    {
        return AdminAuthorization::can($user, 'orders.update');
    }

    public function delete(User $user, Order $order): bool
    {
        return AdminAuthorization::can($user, 'orders.delete');
    }

    public function restore(User $user, Order $order): bool
    {
        return AdminAuthorization::can($user, 'orders.delete');
    }

    public function forceDelete(User $user, Order $order): bool
    {
        return AdminAuthorization::can($user, 'orders.delete');
    }

    public function export(User $user): bool
    {
        return AdminAuthorization::can($user, 'orders.export');
    }

    public function refund(User $user, Order $order): bool
    {
        return AdminAuthorization::can($user, 'orders.refund');
    }
}
