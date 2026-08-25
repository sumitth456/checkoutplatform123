<?php

namespace App\Policies;

use App\Models\AffiliateClick;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class AffiliateClickPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'affiliate_clicks.view');
    }

    public function view(User $user, AffiliateClick $affiliateClick): bool
    {
        return AdminAuthorization::can($user, 'affiliate_clicks.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AffiliateClick $affiliateClick): bool
    {
        return false;
    }

    public function delete(User $user, AffiliateClick $affiliateClick): bool
    {
        return false;
    }
}
