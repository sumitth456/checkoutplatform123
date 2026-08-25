<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class DashboardPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'dashboard.view');
    }

    public function view(User $user): bool
    {
        return AdminAuthorization::can($user, 'dashboard.view');
    }
}
