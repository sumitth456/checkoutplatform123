<?php

namespace App\Policies;

use App\Models\AffiliateConversion;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class AffiliateConversionPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'affiliate_conversions.view');
    }

    public function view(User $user, AffiliateConversion $affiliateConversion): bool
    {
        return AdminAuthorization::can($user, 'affiliate_conversions.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AffiliateConversion $affiliateConversion): bool
    {
        return false;
    }

    public function delete(User $user, AffiliateConversion $affiliateConversion): bool
    {
        return false;
    }
}
