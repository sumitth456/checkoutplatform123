<?php

namespace App\Modules\Admin\Services;

use App\Models\Coupon;

class CouponAdminService
{
    /**
     * @return array{all: int, active: int, scheduled: int, expired: int, inactive: int}
     */
    public function tabCounts(): array
    {
        return [
            'all' => Coupon::query()->count(),
            'active' => Coupon::query()
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                })
                ->where(function ($query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->count(),
            'scheduled' => Coupon::query()
                ->where('is_active', true)
                ->whereNotNull('starts_at')
                ->where('starts_at', '>', now())
                ->count(),
            'expired' => Coupon::query()
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->count(),
            'inactive' => Coupon::query()->where('is_active', false)->count(),
        ];
    }
}
