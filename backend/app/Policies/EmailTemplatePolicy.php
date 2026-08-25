<?php

namespace App\Policies;

use App\Models\EmailTemplate;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class EmailTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'email_templates.manage');
    }

    public function view(User $user, EmailTemplate $template): bool
    {
        return AdminAuthorization::can($user, 'email_templates.manage');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'email_templates.manage');
    }

    public function update(User $user, EmailTemplate $template): bool
    {
        return AdminAuthorization::can($user, 'email_templates.manage');
    }

    public function delete(User $user, EmailTemplate $template): bool
    {
        return AdminAuthorization::can($user, 'email_templates.manage');
    }

    public function sendTest(User $user, EmailTemplate $template): bool
    {
        return AdminAuthorization::can($user, 'email.test');
    }
}
