<?php

namespace App\Policies;

use App\Models\Affiliate;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class AffiliatePolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'affiliates.view');
    }

    public function view(User $user, Affiliate $affiliate): bool
    {
        return AdminAuthorization::can($user, 'affiliates.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'affiliates.create');
    }

    public function update(User $user, Affiliate $affiliate): bool
    {
        return AdminAuthorization::can($user, 'affiliates.update');
    }

    public function delete(User $user, Affiliate $affiliate): bool
    {
        return AdminAuthorization::can($user, 'affiliates.delete');
    }

    public function restore(User $user, Affiliate $affiliate): bool
    {
        return AdminAuthorization::can($user, 'affiliates.delete');
    }

    public function forceDelete(User $user, Affiliate $affiliate): bool
    {
        return AdminAuthorization::can($user, 'affiliates.delete');
    }
}
