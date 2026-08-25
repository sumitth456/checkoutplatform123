<?php

namespace App\Policies;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class PlatformSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'settings.view');
    }

    public function view(User $user, PlatformSetting $platformSetting): bool
    {
        return AdminAuthorization::can($user, 'settings.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PlatformSetting $platformSetting): bool
    {
        return AdminAuthorization::can($user, 'settings.update');
    }

    public function delete(User $user, PlatformSetting $platformSetting): bool
    {
        return false;
    }
}
