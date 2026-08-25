<?php

namespace App\Policies;

use App\Models\CampaignUpsell;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class CampaignUpsellPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'campaigns.merchandising.manage');
    }

    public function view(User $user, CampaignUpsell $campaignUpsell): bool
    {
        unset($campaignUpsell);

        return AdminAuthorization::can($user, 'campaigns.merchandising.manage');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'campaigns.merchandising.manage');
    }

    public function update(User $user, CampaignUpsell $campaignUpsell): bool
    {
        unset($campaignUpsell);

        return AdminAuthorization::can($user, 'campaigns.merchandising.manage');
    }

    public function delete(User $user, CampaignUpsell $campaignUpsell): bool
    {
        unset($campaignUpsell);

        return AdminAuthorization::can($user, 'campaigns.merchandising.manage');
    }
}
