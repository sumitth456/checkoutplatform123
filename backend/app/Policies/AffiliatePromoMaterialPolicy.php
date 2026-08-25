<?php

namespace App\Policies;

use App\Models\AffiliatePromoMaterial;
use App\Models\User;
use App\Support\Admin\AdminAuthorization;

class AffiliatePromoMaterialPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAuthorization::can($user, 'affiliate_promo_materials.view');
    }

    public function view(User $user, AffiliatePromoMaterial $affiliatePromoMaterial): bool
    {
        return AdminAuthorization::can($user, 'affiliate_promo_materials.view');
    }

    public function create(User $user): bool
    {
        return AdminAuthorization::can($user, 'affiliate_promo_materials.create');
    }

    public function update(User $user, AffiliatePromoMaterial $affiliatePromoMaterial): bool
    {
        return AdminAuthorization::can($user, 'affiliate_promo_materials.update');
    }

    public function delete(User $user, AffiliatePromoMaterial $affiliatePromoMaterial): bool
    {
        return AdminAuthorization::can($user, 'affiliate_promo_materials.delete');
    }

    public function restore(User $user, AffiliatePromoMaterial $affiliatePromoMaterial): bool
    {
        return AdminAuthorization::can($user, 'affiliate_promo_materials.delete');
    }

    public function forceDelete(User $user, AffiliatePromoMaterial $affiliatePromoMaterial): bool
    {
        return AdminAuthorization::can($user, 'affiliate_promo_materials.delete');
    }
}
