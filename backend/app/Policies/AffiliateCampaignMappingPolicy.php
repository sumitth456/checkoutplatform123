<?php

namespace App\Policies;

use App\Models\AffiliateCampaignMapping;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class AffiliateCampaignMappingPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'affiliate_campaign_mappings.view');
    }

    public function view(User $user, AffiliateCampaignMapping $affiliateCampaignMapping): bool
    {
        return AdminAuthorization::can($user, 'affiliate_campaign_mappings.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'affiliate_campaign_mappings.create');
    }

    public function update(User $user, AffiliateCampaignMapping $affiliateCampaignMapping): bool
    {
        return AdminAuthorization::can($user, 'affiliate_campaign_mappings.update');
    }

    public function delete(User $user, AffiliateCampaignMapping $affiliateCampaignMapping): bool
    {
        return AdminAuthorization::can($user, 'affiliate_campaign_mappings.delete');
    }
}
