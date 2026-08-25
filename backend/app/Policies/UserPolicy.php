<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'users.view');
    }

    public function view(User $user, User $model): bool
    {
        return AdminAuthorization::can($user, 'users.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'users.create');
    }

    public function update(User $user, User $model): bool
    {
        return AdminAuthorization::can($user, 'users.update');
    }

    public function delete(User $user, User $model): bool
    {
        return AdminAuthorization::can($user, 'users.delete');
    }

    public function restore(User $user, User $model): bool
    {
        return AdminAuthorization::can($user, 'users.delete');
    }

    public function forceDelete(User $user, User $model): bool
    {
        return AdminAuthorization::can($user, 'users.delete');
    }

    public function export(User $user): bool
    {
        return AdminAuthorization::can($user, 'users.export');
    }

    public function impersonate(User $user, User $model): bool
    {
        unset($model);

        return AdminAuthorization::can($user, 'users.impersonate');
    }

    public function duplicate(User $user, User $model): bool
    {
        unset($model);

        return AdminAuthorization::can($user, 'users.duplicate');
    }

    public function resetPassword(User $user, User $model): bool
    {
        unset($model);

        return AdminAuthorization::can($user, 'users.reset_password');
    }
}
