<?php

namespace App\Modules\Order\Services;

use App\Models\Campaign;
use App\Models\Coupon;
use Illuminate\Validation\ValidationException;

final class CouponValidationService
{
    /**
     * @return array{coupon: ?Coupon, discount_total: float, message: ?string}
     */
    public function validate(?string $code, float $subtotal, ?Campaign $campaign = null): array
    {
        if (blank($code)) {
            return ['coupon' => null, 'discount_total' => 0.0, 'message' => null];
        }

        $coupon = Coupon::query()
            ->where('code', strtoupper(trim($code)))
            ->where('is_active', true)
            ->first();

        if ($coupon === null) {
            throw ValidationException::withMessages([
                'coupon_code' => ['Invalid coupon code.'],
            ]);
        }

        if ($coupon->isExpired() || $coupon->isScheduled()) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon is not currently available.'],
            ]);
        }

        if ($coupon->remainingUses() === 0) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon has reached its usage limit.'],
            ]);
        }

        if ($coupon->minimum_order_amount !== null && $subtotal < (float) $coupon->minimum_order_amount) {
            throw ValidationException::withMessages([
                'coupon_code' => ['Order does not meet the minimum amount for this coupon.'],
            ]);
        }

        $discount = $this->calculateDiscount($coupon, $subtotal);

        return [
            'coupon' => $coupon,
            'discount_total' => $discount,
            'message' => 'Coupon applied.',
        ];
    }

    private function calculateDiscount(Coupon $coupon, float $subtotal): float
    {
        $discount = match ($coupon->type) {
            'percentage' => round($subtotal * ((float) $coupon->value / 100), 2),
            'fixed' => round((float) $coupon->value, 2),
            default => 0.0,
        };

        if ($coupon->maximum_discount_amount !== null) {
            $discount = min($discount, (float) $coupon->maximum_discount_amount);
        }

        return min($discount, $subtotal);
    }
}
