<?php

namespace App\Policies;

use App\Models\AffiliateCommission;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class AffiliateCommissionPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'affiliate_commissions.view');
    }

    public function view(User $user, AffiliateCommission $affiliateCommission): bool
    {
        return AdminAuthorization::can($user, 'affiliate_commissions.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AffiliateCommission $affiliateCommission): bool
    {
        return false;
    }

    public function delete(User $user, AffiliateCommission $affiliateCommission): bool
    {
        return false;
    }
}
