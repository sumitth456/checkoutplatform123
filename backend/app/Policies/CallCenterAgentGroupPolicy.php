<?php

namespace App\Policies;

use App\Models\CallCenterAgentGroup;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class CallCenterAgentGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'call_center_agents.view');
    }

    public function view(User $user, CallCenterAgentGroup $group): bool
    {
        return AdminAuthorization::can($user, 'call_center_agents.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'call_center_agents.manage');
    }

    public function update(User $user, CallCenterAgentGroup $group): bool
    {
        return AdminAuthorization::can($user, 'call_center_agents.manage');
    }

    public function delete(User $user, CallCenterAgentGroup $group): bool
    {
        return AdminAuthorization::can($user, 'call_center_agents.manage');
    }

    public function restore(User $user, CallCenterAgentGroup $group): bool
    {
        return AdminAuthorization::can($user, 'call_center_agents.manage');
    }

    public function forceDelete(User $user, CallCenterAgentGroup $group): bool
    {
        return AdminAuthorization::can($user, 'call_center_agents.manage');
    }
}
