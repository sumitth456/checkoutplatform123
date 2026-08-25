<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class RebillQueuePolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'rebill_queue.view');
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return AdminAuthorization::can($user, 'rebill_queue.view');
    }

    public function manage(User $user, Subscription $subscription): bool
    {
        return AdminAuthorization::can($user, 'rebill_queue.manage');
    }
}
