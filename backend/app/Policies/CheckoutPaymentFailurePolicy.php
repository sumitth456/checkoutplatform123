<?php

namespace App\Policies;

use App\Models\CheckoutPaymentFailure;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class CheckoutPaymentFailurePolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'logs.view');
    }

    public function view(User $user, CheckoutPaymentFailure $failure): bool
    {
        unset($failure);

        return AdminAuthorization::can($user, 'logs.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, CheckoutPaymentFailure $failure): bool
    {
        unset($failure);

        return false;
    }

    public function delete(User $user, CheckoutPaymentFailure $failure): bool
    {
        unset($failure);

        return false;
    }
}
