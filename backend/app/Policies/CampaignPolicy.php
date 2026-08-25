<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'campaigns.view');
    }

    public function view(User $user, Campaign $campaign): bool
    {
        unset($campaign);

        return AdminAuthorization::can($user, 'campaigns.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'campaigns.create');
    }

    public function update(User $user, Campaign $campaign): bool
    {
        unset($campaign);

        return AdminAuthorization::can($user, 'campaigns.update');
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        unset($campaign);

        return AdminAuthorization::can($user, 'campaigns.delete');
    }

    public function restore(User $user, Campaign $campaign): bool
    {
        unset($campaign);

        return AdminAuthorization::can($user, 'campaigns.delete');
    }

    public function forceDelete(User $user, Campaign $campaign): bool
    {
        unset($campaign);

        return AdminAuthorization::can($user, 'campaigns.delete');
    }

    public function export(User $user): bool
    {
        return AdminAuthorization::can($user, 'campaigns.export');
    }

    public function duplicate(User $user, Campaign $campaign): bool
    {
        unset($campaign);

        return AdminAuthorization::can($user, 'campaigns.duplicate');
    }

    public function archive(User $user, Campaign $campaign): bool
    {
        unset($campaign);

        return AdminAuthorization::can($user, 'campaigns.archive');
    }
}
