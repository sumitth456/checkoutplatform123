<?php

namespace App\Policies;

use App\Models\CallDisposition;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class CallDispositionPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'call_dispositions.view');
    }

    public function view(User $user, CallDisposition $disposition): bool
    {
        return AdminAuthorization::can($user, 'call_dispositions.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'call_dispositions.manage');
    }

    public function update(User $user, CallDisposition $disposition): bool
    {
        return AdminAuthorization::can($user, 'call_dispositions.manage');
    }

    public function delete(User $user, CallDisposition $disposition): bool
    {
        return AdminAuthorization::can($user, 'call_dispositions.manage');
    }

    public function restore(User $user, CallDisposition $disposition): bool
    {
        return AdminAuthorization::can($user, 'call_dispositions.manage');
    }

    public function forceDelete(User $user, CallDisposition $disposition): bool
    {
        return AdminAuthorization::can($user, 'call_dispositions.manage');
    }
}
