<?php

namespace App\Policies;

use App\Models\EmailProvider;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class EmailProviderPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'email_providers.manage');
    }

    public function view(User $user, EmailProvider $provider): bool
    {
        return AdminAuthorization::can($user, 'email_providers.manage');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'email_providers.manage');
    }

    public function update(User $user, EmailProvider $provider): bool
    {
        return AdminAuthorization::can($user, 'email_providers.manage');
    }

    public function delete(User $user, EmailProvider $provider): bool
    {
        return AdminAuthorization::can($user, 'email_providers.manage');
    }

    public function test(User $user, EmailProvider $provider): bool
    {
        return AdminAuthorization::can($user, 'email.test');
    }
}
