<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class ShippingCarrierPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'shipping_carriers.view');
    }

    public function view(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'shipping_carriers.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'shipping_carriers.create');
    }

    public function update(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'shipping_carriers.update');
    }

    public function delete(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'shipping_carriers.delete');
    }

    public function restore(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'shipping_carriers.delete');
    }

    public function forceDelete(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'shipping_carriers.delete');
    }
}
