<?php

namespace App\Policies;

use App\Models\StripeWebhookEvent;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class StripeWebhookEventPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'logs.view');
    }

    public function view(User $user, StripeWebhookEvent $stripeWebhookEvent): bool
    {
        return AdminAuthorization::can($user, 'logs.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, StripeWebhookEvent $stripeWebhookEvent): bool
    {
        return false;
    }

    public function delete(User $user, StripeWebhookEvent $stripeWebhookEvent): bool
    {
        return false;
    }

    public function export(User $user): bool
    {
        return AdminAuthorization::can($user, 'logs.export');
    }
}
