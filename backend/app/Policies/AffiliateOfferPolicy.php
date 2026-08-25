<?php

namespace App\Policies;

use App\Models\AffiliateOffer;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class AffiliateOfferPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'affiliate_offers.view');
    }

    public function view(User $user, AffiliateOffer $affiliateOffer): bool
    {
        return AdminAuthorization::can($user, 'affiliate_offers.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'affiliate_offers.create');
    }

    public function update(User $user, AffiliateOffer $affiliateOffer): bool
    {
        return AdminAuthorization::can($user, 'affiliate_offers.update');
    }

    public function delete(User $user, AffiliateOffer $affiliateOffer): bool
    {
        return AdminAuthorization::can($user, 'affiliate_offers.delete');
    }

    public function restore(User $user, AffiliateOffer $affiliateOffer): bool
    {
        return AdminAuthorization::can($user, 'affiliate_offers.delete');
    }

    public function forceDelete(User $user, AffiliateOffer $affiliateOffer): bool
    {
        return AdminAuthorization::can($user, 'affiliate_offers.delete');
    }
}
