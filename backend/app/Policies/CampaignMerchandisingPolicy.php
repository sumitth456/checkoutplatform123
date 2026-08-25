<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class CampaignMerchandisingPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'campaigns.merchandising.manage');
    }

    public function view(User $user, mixed $model): bool
    {
        unset($model);

        return AdminAuthorization::can($user, 'campaigns.merchandising.manage');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'campaigns.merchandising.manage');
    }

    public function update(User $user, mixed $model): bool
    {
        unset($model);

        return AdminAuthorization::can($user, 'campaigns.merchandising.manage');
    }

    public function delete(User $user, mixed $model): bool
    {
        unset($model);

        return AdminAuthorization::can($user, 'campaigns.merchandising.manage');
    }
}
