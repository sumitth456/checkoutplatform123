<?php

namespace App\Modules\Order\Services;

use App\Modules\Order\DTOs\CreateFunnelOrderDTO;
use App\Modules\Order\DTOs\OrderTotalsDTO;

class CouponApplicationService
{
    public function __construct(
        private readonly CouponValidationService $validation,
    ) {}

    public function apply(CreateFunnelOrderDTO $dto, OrderTotalsDTO $totals): OrderTotalsDTO
    {
        if (blank($dto->couponCode)) {
            return $totals;
        }

        $result = $this->validation->validate($dto->couponCode, $totals->subtotal);

        return new OrderTotalsDTO(
            currency: $totals->currency,
            subtotal: $totals->subtotal,
            discountTotal: $result['discount_total'],
            taxTotal: $totals->taxTotal,
            shippingTotal: $totals->shippingTotal,
            grandTotal: max(0, round(
                $totals->subtotal - $result['discount_total'] + $totals->taxTotal + $totals->shippingTotal,
                2,
            )),
            countryCode: $totals->countryCode,
        );
    }
}
