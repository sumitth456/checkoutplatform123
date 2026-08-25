<?php

namespace App\Policies;

use App\Models\AffiliateTrackingLink;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class AffiliateTrackingLinkPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'affiliate_tracking_links.view');
    }

    public function view(User $user, AffiliateTrackingLink $affiliateTrackingLink): bool
    {
        return AdminAuthorization::can($user, 'affiliate_tracking_links.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'affiliate_tracking_links.create');
    }

    public function update(User $user, AffiliateTrackingLink $affiliateTrackingLink): bool
    {
        return AdminAuthorization::can($user, 'affiliate_tracking_links.update');
    }

    public function delete(User $user, AffiliateTrackingLink $affiliateTrackingLink): bool
    {
        return AdminAuthorization::can($user, 'affiliate_tracking_links.delete');
    }

    public function restore(User $user, AffiliateTrackingLink $affiliateTrackingLink): bool
    {
        return AdminAuthorization::can($user, 'affiliate_tracking_links.delete');
    }

    public function forceDelete(User $user, AffiliateTrackingLink $affiliateTrackingLink): bool
    {
        return AdminAuthorization::can($user, 'affiliate_tracking_links.delete');
    }
}
