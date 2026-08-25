<?php

namespace App\Modules\Campaign\Services;

use App\Models\Campaign;
use App\Models\CampaignOrderBump;
use App\Models\Coupon;
use App\Models\Variant;
use App\Modules\Order\Actions\CalculateOrderTotalsAction;
use App\Modules\Order\Actions\ResolveLineItemAction;
use App\Modules\Order\DTOs\CreateFunnelOrderDTO;
use App\Modules\Order\DTOs\CustomerDataDTO;
use App\Modules\Order\DTOs\FunnelLineItemDTO;
use App\Modules\Order\DTOs\OrderAttributionDTO;
use App\Modules\Order\DTOs\RequestContextDTO;
use App\Modules\Order\Enums\DeviceType;
use App\Modules\Order\Services\CouponValidationService;
use Illuminate\Validation\ValidationException;

final class CampaignCheckoutCalculateService
{
    public function __construct(
        private readonly ResolveLineItemAction $resolveLineItem,
        private readonly CalculateOrderTotalsAction $calculateTotals,
        private readonly CouponValidationService $coupons,
        private readonly CampaignCheckoutPricingService $pricing,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function calculate(Campaign $campaign, array $input): array
    {
        $items = collect($input['items'] ?? [])
            ->map(fn (array $item): FunnelLineItemDTO => FunnelLineItemDTO::fromArray($item))
            ->all();

        $resolved = [];

        foreach ($items as $index => $item) {
            $resolved[] = $this->resolveLineItem->execute($item, $index, $campaign);
        }

        $subtotal = round(collect($resolved)->sum('lineTotal'), 2);
        $currency = strtoupper((string) ($campaign->currency ?: collect($resolved)->first()?->currency ?: 'USD'));

        $couponResult = $this->coupons->validate(
            code: isset($input['coupon_code']) ? (string) $input['coupon_code'] : null,
            subtotal: $subtotal,
            campaign: $campaign,
        );

        $shippingTotal = $this->resolveShippingTotal($campaign, $input, $subtotal);
        $taxTotal = $this->resolveTaxTotal($campaign, $input, max(0, $subtotal - $couponResult['discount_total']));

        $dto = new CreateFunnelOrderDTO(
            customer: new CustomerDataDTO('', null, null, null),
            lineItems: $items,
            context: new RequestContextDTO(null, null, DeviceType::Unknown),
            attribution: new OrderAttributionDTO(platformCampaignId: $campaign->id),
            discountTotal: $couponResult['discount_total'],
            taxTotal: $taxTotal,
            shippingTotal: $shippingTotal,
            currency: $currency,
            countryCode: isset($input['shipping_address']['country'])
                ? strtoupper((string) $input['shipping_address']['country'])
                : null,
            couponCode: $couponResult['coupon']?->code,
            couponId: $couponResult['coupon']?->id,
        );

        $totals = $this->calculateTotals->execute($dto, $resolved);

        return [
            'currency' => $totals->currency,
            'subtotal' => number_format($totals->subtotal, 2, '.', ''),
            'discount_total' => number_format($totals->discountTotal, 2, '.', ''),
            'tax_total' => number_format($totals->taxTotal, 2, '.', ''),
            'shipping_total' => number_format($totals->shippingTotal, 2, '.', ''),
            'grand_total' => number_format($totals->grandTotal, 2, '.', ''),
            'coupon' => $couponResult['coupon'] ? [
                'code' => $couponResult['coupon']->code,
                'discount' => number_format($couponResult['discount_total'], 2, '.', ''),
            ] : null,
            'items' => collect($resolved)->map(fn ($item): array => [
                'variant_uuid' => $item->variant->uuid,
                'sku' => $item->sku,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit_price' => number_format($item->unitPrice, 2, '.', ''),
                'line_total' => number_format($item->lineTotal, 2, '.', ''),
                'billing_product' => $this->resolveBillingForVariant($campaign, $item->variant),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveShippingTotal(Campaign $campaign, array $input, float $subtotal): float
    {
        $shipping = (array) ($campaign->metadata['shipping'] ?? []);
        $flat = (float) ($shipping['price'] ?? $shipping['flat_rate'] ?? 0);

        if (($shipping['free_over'] ?? null) !== null && $subtotal >= (float) $shipping['free_over']) {
            return 0.0;
        }

        return round(max(0, $flat), 2);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveTaxTotal(Campaign $campaign, array $input, float $taxableAmount): float
    {
        $taxes = (array) ($campaign->metadata['taxes'] ?? []);
        $rate = (float) ($taxes['rate'] ?? 0);

        if ($rate <= 0) {
            return 0.0;
        }

        return round(max(0, $taxableAmount * ($rate / 100)), 2);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveBillingForVariant(Campaign $campaign, Variant $variant): ?array
    {
        $line = $this->pricing->findCampaignProductLine($campaign, $variant);

        return $line ? $this->pricing->resolveBillingProduct($line, $variant) : null;
    }
}
