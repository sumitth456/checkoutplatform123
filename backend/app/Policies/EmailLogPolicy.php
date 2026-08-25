<?php

namespace App\Policies;

use App\Models\EmailLog;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class EmailLogPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'email_logs.view');
    }

    public function view(User $user, EmailLog $log): bool
    {
        return AdminAuthorization::can($user, 'email_logs.view');
    }

    public function resend(User $user, EmailLog $log): bool
    {
        return AdminAuthorization::can($user, 'email_logs.resend');
    }

    public function retry(User $user, EmailLog $log): bool
    {
        return AdminAuthorization::can($user, 'email_logs.resend');
    }
}
