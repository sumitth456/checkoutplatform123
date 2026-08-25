<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Variant;
use App\Support\Admin\AdminAuthorization;

class VariantPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'products.view');
    }

    public function view(User $user, Variant $variant): bool
    {
        return AdminAuthorization::can($user, 'products.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'products.update');
    }

    public function update(User $user, Variant $variant): bool
    {
        return AdminAuthorization::can($user, 'products.update');
    }

    public function delete(User $user, Variant $variant): bool
    {
        return AdminAuthorization::can($user, 'products.update');
    }

    public function restore(User $user, Variant $variant): bool
    {
        return AdminAuthorization::can($user, 'products.update');
    }

    public function forceDelete(User $user, Variant $variant): bool
    {
        return AdminAuthorization::can($user, 'products.delete');
    }
}
