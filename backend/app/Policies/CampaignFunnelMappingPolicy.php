<?php

namespace App\Policies;

use App\Models\CampaignFunnelMapping;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class CampaignFunnelMappingPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'campaigns.funnels.manage');
    }

    public function view(User $user, CampaignFunnelMapping $mapping): bool
    {
        unset($mapping);

        return AdminAuthorization::can($user, 'campaigns.funnels.manage');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'campaigns.funnels.manage');
    }

    public function update(User $user, CampaignFunnelMapping $mapping): bool
    {
        unset($mapping);

        return AdminAuthorization::can($user, 'campaigns.funnels.manage');
    }

    public function delete(User $user, CampaignFunnelMapping $mapping): bool
    {
        unset($mapping);

        return AdminAuthorization::can($user, 'campaigns.funnels.manage');
    }
}
