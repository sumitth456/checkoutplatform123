<?php

namespace App\Modules\Order\DTOs;

readonly class AppliedCouponDTO
{
    public function __construct(
        public int $couponId,
        public string $code,
        public float $discountAmount,
    ) {}
}
