<?php

namespace App\Policies;

use App\Models\CallCenter;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class CallCenterPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'call_centers.view');
    }

    public function view(User $user, CallCenter $callCenter): bool
    {
        return AdminAuthorization::can($user, 'call_centers.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'call_centers.create');
    }

    public function update(User $user, CallCenter $callCenter): bool
    {
        return AdminAuthorization::can($user, 'call_centers.update');
    }

    public function delete(User $user, CallCenter $callCenter): bool
    {
        return AdminAuthorization::can($user, 'call_centers.delete');
    }

    public function restore(User $user, CallCenter $callCenter): bool
    {
        return AdminAuthorization::can($user, 'call_centers.delete');
    }

    public function forceDelete(User $user, CallCenter $callCenter): bool
    {
        return AdminAuthorization::can($user, 'call_centers.delete');
    }
}
