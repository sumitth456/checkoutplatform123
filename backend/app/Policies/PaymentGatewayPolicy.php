<?php

namespace App\Policies;

use App\Models\PaymentGatewayConfig;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class PaymentGatewayPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'payment_gateways.view');
    }

    public function view(User $user, PaymentGatewayConfig $gateway): bool
    {
        return AdminAuthorization::can($user, 'payment_gateways.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'payment_gateways.create');
    }

    public function update(User $user, PaymentGatewayConfig $gateway): bool
    {
        return AdminAuthorization::can($user, 'payment_gateways.update');
    }

    public function delete(User $user, PaymentGatewayConfig $gateway): bool
    {
        return AdminAuthorization::can($user, 'payment_gateways.delete');
    }

    public function restore(User $user, PaymentGatewayConfig $gateway): bool
    {
        return AdminAuthorization::can($user, 'payment_gateways.delete');
    }

    public function forceDelete(User $user, PaymentGatewayConfig $gateway): bool
    {
        return AdminAuthorization::can($user, 'payment_gateways.delete');
    }

    public function export(User $user): bool
    {
        return AdminAuthorization::can($user, 'payment_gateways.export');
    }
}
