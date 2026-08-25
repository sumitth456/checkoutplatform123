<?php

namespace App\Policies;

use App\Models\Coupon;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class CouponPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'coupons.view');
    }

    public function view(User $user, Coupon $coupon): bool
    {
        return AdminAuthorization::can($user, 'coupons.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'coupons.create');
    }

    public function update(User $user, Coupon $coupon): bool
    {
        return AdminAuthorization::can($user, 'coupons.update');
    }

    public function delete(User $user, Coupon $coupon): bool
    {
        return AdminAuthorization::can($user, 'coupons.delete');
    }

    public function restore(User $user, Coupon $coupon): bool
    {
        return AdminAuthorization::can($user, 'coupons.delete');
    }

    public function forceDelete(User $user, Coupon $coupon): bool
    {
        return AdminAuthorization::can($user, 'coupons.delete');
    }

    public function export(User $user): bool
    {
        return AdminAuthorization::can($user, 'coupons.export');
    }
}
