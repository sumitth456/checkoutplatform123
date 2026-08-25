<?php

namespace App\Policies;

use App\Models\LandingPageTemplate;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class LandingPageTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'landing_pages.view');
    }

    public function view(User $user, LandingPageTemplate $template): bool
    {
        return AdminAuthorization::can($user, 'landing_pages.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'landing_pages.templates.manage');
    }

    public function update(User $user, LandingPageTemplate $template): bool
    {
        return AdminAuthorization::can($user, 'landing_pages.templates.manage');
    }

    public function delete(User $user, LandingPageTemplate $template): bool
    {
        return AdminAuthorization::can($user, 'landing_pages.templates.manage');
    }
}
