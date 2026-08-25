<?php

namespace App\Policies;

use App\Models\AdminActivityLog;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class AdminActivityLogPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'logs.view');
    }

    public function view(User $user, AdminActivityLog $adminActivityLog): bool
    {
        return AdminAuthorization::can($user, 'logs.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AdminActivityLog $adminActivityLog): bool
    {
        return false;
    }

    public function delete(User $user, AdminActivityLog $adminActivityLog): bool
    {
        return false;
    }

    public function export(User $user): bool
    {
        return AdminAuthorization::can($user, 'logs.export');
    }
}
