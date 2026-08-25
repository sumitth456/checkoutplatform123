<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class FulfillmentHousePolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'fulfillment_houses.view');
    }

    public function view(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'fulfillment_houses.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'fulfillment_houses.create');
    }

    public function update(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'fulfillment_houses.update');
    }

    public function delete(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'fulfillment_houses.delete');
    }

    public function restore(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'fulfillment_houses.delete');
    }

    public function forceDelete(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'fulfillment_houses.delete');
    }
}
