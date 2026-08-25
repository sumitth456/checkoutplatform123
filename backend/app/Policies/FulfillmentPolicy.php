<?php

namespace App\Policies;

use App\Models\Fulfillment;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class FulfillmentPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'fulfillment.view')
            || AdminAuthorization::can($user, 'delay_fulfillment.view');
    }

    public function view(User $user, Fulfillment $fulfillment): bool
    {
        return AdminAuthorization::can($user, 'fulfillment.view')
            || AdminAuthorization::can($user, 'delay_fulfillment.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Fulfillment $fulfillment): bool
    {
        return false;
    }

    public function delete(User $user, Fulfillment $fulfillment): bool
    {
        return false;
    }

    public function manage(User $user, Fulfillment $fulfillment): bool
    {
        return AdminAuthorization::can($user, 'fulfillment.manage')
            || AdminAuthorization::can($user, 'delay_fulfillment.manage');
    }

    public function export(User $user): bool
    {
        return AdminAuthorization::can($user, 'fulfillment.view')
            || AdminAuthorization::can($user, 'delay_fulfillment.export');
    }
}
