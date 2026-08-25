<?php

namespace App\Policies;

use App\Models\AffiliatePayout;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class AffiliatePayoutPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'affiliate_payouts.view');
    }

    public function view(User $user, AffiliatePayout $affiliatePayout): bool
    {
        return AdminAuthorization::can($user, 'affiliate_payouts.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'affiliate_payouts.manage');
    }

    public function update(User $user, AffiliatePayout $affiliatePayout): bool
    {
        return AdminAuthorization::can($user, 'affiliate_payouts.manage');
    }

    public function delete(User $user, AffiliatePayout $affiliatePayout): bool
    {
        return AdminAuthorization::can($user, 'affiliate_payouts.manage');
    }
}
