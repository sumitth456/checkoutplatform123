<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'products.view');
    }

    public function view(User $user, Product $product): bool
    {
        return AdminAuthorization::can($user, 'products.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'products.create');
    }

    public function update(User $user, Product $product): bool
    {
        return AdminAuthorization::can($user, 'products.update');
    }

    public function delete(User $user, Product $product): bool
    {
        return AdminAuthorization::can($user, 'products.delete');
    }

    public function restore(User $user, Product $product): bool
    {
        return AdminAuthorization::can($user, 'products.delete');
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return AdminAuthorization::can($user, 'products.delete');
    }

    public function export(User $user): bool
    {
        return AdminAuthorization::can($user, 'products.export');
    }
}
