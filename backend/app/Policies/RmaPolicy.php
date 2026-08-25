<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class RmaPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'fulfillment.view');
    }

    public function view(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'fulfillment.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'fulfillment.manage');
    }

    public function update(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'fulfillment.manage');
    }

    public function delete(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'fulfillment.manage');
    }

    public function restore(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'fulfillment.manage');
    }

    public function forceDelete(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'fulfillment.manage');
    }
}
