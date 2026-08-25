<?php

namespace App\Policies;

use App\Models\CallCenterAgent;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class CallCenterAgentPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'call_center_agents.view');
    }

    public function view(User $user, CallCenterAgent $agent): bool
    {
        return AdminAuthorization::can($user, 'call_center_agents.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'call_center_agents.manage');
    }

    public function update(User $user, CallCenterAgent $agent): bool
    {
        return AdminAuthorization::can($user, 'call_center_agents.manage');
    }

    public function delete(User $user, CallCenterAgent $agent): bool
    {
        return AdminAuthorization::can($user, 'call_center_agents.manage');
    }

    public function restore(User $user, CallCenterAgent $agent): bool
    {
        return AdminAuthorization::can($user, 'call_center_agents.manage');
    }

    public function forceDelete(User $user, CallCenterAgent $agent): bool
    {
        return AdminAuthorization::can($user, 'call_center_agents.manage');
    }
}
