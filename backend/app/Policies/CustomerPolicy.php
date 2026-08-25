<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return AdminAuthorization::can($user, 'customers.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return AdminAuthorization::can($user, 'customers.update');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return AdminAuthorization::can($user, 'customers.delete');
    }

    public function restore(User $user, Customer $customer): bool
    {
        return AdminAuthorization::can($user, 'customers.delete');
    }

    public function forceDelete(User $user, Customer $customer): bool
    {
        return AdminAuthorization::can($user, 'customers.delete');
    }

    public function export(User $user): bool
    {
        return AdminAuthorization::can($user, 'customers.export');
    }
}
