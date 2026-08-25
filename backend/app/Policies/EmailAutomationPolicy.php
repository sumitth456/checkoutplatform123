<?php

namespace App\Policies;

use App\Models\EmailAutomation;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class EmailAutomationPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'email_automations.manage');
    }

    public function view(User $user, EmailAutomation $automation): bool
    {
        return AdminAuthorization::can($user, 'email_automations.manage');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'email_automations.manage');
    }

    public function update(User $user, EmailAutomation $automation): bool
    {
        return AdminAuthorization::can($user, 'email_automations.manage');
    }

    public function delete(User $user, EmailAutomation $automation): bool
    {
        return AdminAuthorization::can($user, 'email_automations.manage');
    }
}
