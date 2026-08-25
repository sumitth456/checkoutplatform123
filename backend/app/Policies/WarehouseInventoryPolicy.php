<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class WarehouseInventoryPolicy
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
        return AdminAuthorization::can($user, 'warehouses.update');
    }

    public function update(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'warehouses.update');
    }

    public function delete(User $user, mixed $record): bool
    {
        return AdminAuthorization::can($user, 'warehouses.update');
    }
}
