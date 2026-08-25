<?php

namespace App\Modules\Order\Actions;

use App\Modules\Order\DTOs\CreateFunnelOrderDTO;
use App\Modules\Order\DTOs\OrderTotalsDTO;
use App\Modules\Order\DTOs\ResolvedLineItemDTO;

class CalculateOrderTotalsAction
{
    /**
     * @param  list<ResolvedLineItemDTO>  $lineItems
     */
    public function execute(CreateFunnelOrderDTO $dto, array $lineItems): OrderTotalsDTO
    {
        $subtotal = round(collect($lineItems)->sum('lineTotal'), 2);
        $currency = $dto->currency
            ?? collect($lineItems)->first()?->currency
            ?? 'USD';

        $countryCode = $dto->countryCode
            ?? $dto->shippingAddress?->country
            ?? $dto->billingAddress?->country;

        $grandTotal = max(0, round(
            $subtotal - $dto->discountTotal + $dto->taxTotal + $dto->shippingTotal,
            2,
        ));

        return new OrderTotalsDTO(
            currency: $currency,
            subtotal: $subtotal,
            discountTotal: $dto->discountTotal,
            taxTotal: $dto->taxTotal,
            shippingTotal: $dto->shippingTotal,
            grandTotal: $grandTotal,
            countryCode: $countryCode,
        );
    }
}
