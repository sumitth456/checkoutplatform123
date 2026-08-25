<?php

namespace App\Policies;

use App\Models\CallLog;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class CallLogPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'call_logs.view');
    }

    public function view(User $user, CallLog $callLog): bool
    {
        return AdminAuthorization::can($user, 'call_logs.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'call_logs.view');
    }

    public function update(User $user, CallLog $callLog): bool
    {
        return AdminAuthorization::can($user, 'call_logs.view');
    }

    public function delete(User $user, CallLog $callLog): bool
    {
        return AdminAuthorization::can($user, 'call_logs.view');
    }

    public function export(User $user): bool
    {
        return AdminAuthorization::can($user, 'call_logs.export');
    }
}
