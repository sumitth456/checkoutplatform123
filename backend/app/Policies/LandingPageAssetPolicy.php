<?php

namespace App\Policies;

use App\Models\LandingPageAsset;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class LandingPageAssetPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'landing_pages.view');
    }

    public function view(User $user, LandingPageAsset $landingPageAsset): bool
    {
        return AdminAuthorization::can($user, 'landing_pages.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'landing_pages.assets.manage');
    }

    public function update(User $user, LandingPageAsset $landingPageAsset): bool
    {
        return AdminAuthorization::can($user, 'landing_pages.assets.manage');
    }

    public function delete(User $user, LandingPageAsset $landingPageAsset): bool
    {
        return AdminAuthorization::can($user, 'landing_pages.assets.manage');
    }
}
