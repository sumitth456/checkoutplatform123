<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'warehouses.view');
    }

    public function view(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'warehouses.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'warehouses.create');
    }

    public function update(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'warehouses.update');
    }

    public function delete(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'warehouses.delete');
    }

    public function restore(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'warehouses.delete');
    }

    public function forceDelete(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'warehouses.delete');
    }
}
