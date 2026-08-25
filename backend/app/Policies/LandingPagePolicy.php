<?php

namespace App\Policies;

use App\Models\LandingPage;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class LandingPagePolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'landing_pages.view');
    }

    public function view(User $user, LandingPage $landingPage): bool
    {
        unset($landingPage);

        return AdminAuthorization::can($user, 'landing_pages.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'landing_pages.create');
    }

    public function update(User $user, LandingPage $landingPage): bool
    {
        unset($landingPage);

        return AdminAuthorization::can($user, 'landing_pages.update');
    }

    public function delete(User $user, LandingPage $landingPage): bool
    {
        unset($landingPage);

        return AdminAuthorization::can($user, 'landing_pages.delete');
    }

    public function restore(User $user, LandingPage $landingPage): bool
    {
        unset($landingPage);

        return AdminAuthorization::can($user, 'landing_pages.update');
    }

    public function forceDelete(User $user, LandingPage $landingPage): bool
    {
        unset($landingPage);

        return AdminAuthorization::can($user, 'landing_pages.delete');
    }
}
