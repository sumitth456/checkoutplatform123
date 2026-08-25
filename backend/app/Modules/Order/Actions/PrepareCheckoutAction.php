<?php

namespace App\Modules\Order\Actions;

use App\Modules\Affiliate\Services\AffiliateAttributionResolver;
use App\Modules\Order\DTOs\CreateFunnelOrderDTO;
use App\Modules\Order\DTOs\OrderAttributionDTO;
use App\Modules\Order\DTOs\PreparedCheckoutDTO;
use App\Modules\Order\Services\CouponValidationService;

class PrepareCheckoutAction
{
    public function __construct(
        private readonly ResolveFunnelAction $resolveFunnel,
        private readonly ResolveCampaignCheckoutAction $resolveCampaign,
        private readonly ResolveLineItemAction $resolveLineItem,
        private readonly VerifyInventoryAction $verifyInventory,
        private readonly CalculateOrderTotalsAction $calculateTotals,
        private readonly ResolveOrCreateCustomerAction $resolveCustomer,
        private readonly CouponValidationService $coupons,
        private readonly AffiliateAttributionResolver $affiliateAttribution,
    ) {}

    public function execute(CreateFunnelOrderDTO $dto): PreparedCheckoutDTO
    {
        $campaign = $this->resolveCampaign->execute($dto);
        $attribution = $this->mergeCampaignAttribution($this->resolveFunnel->execute($dto), $campaign, $dto);
        $attribution = $this->affiliateAttribution->mergeIntoAttribution(
            $attribution,
            $dto->metadata,
            $dto->couponCode,
        );
        $resolvedItems = [];

        foreach ($dto->lineItems as $index => $lineItem) {
            $resolved = $this->resolveLineItem->execute($lineItem, $index, $campaign);
            $this->verifyInventory->execute($resolved, $index);
            $resolvedItems[] = $resolved;
        }

        $customer = $this->resolveCustomer->execute($dto);
        $subtotal = round(collect($resolvedItems)->sum('lineTotal'), 2);

        $couponResult = $this->coupons->validate($dto->couponCode, $subtotal, $campaign);

        $discountTotal = $couponResult['discount_total'] > 0
            ? $couponResult['discount_total']
            : $dto->discountTotal;

        $enrichedDto = new CreateFunnelOrderDTO(
            customer: $dto->customer,
            lineItems: $dto->lineItems,
            context: $dto->context,
            attribution: $attribution,
            metadata: $this->enrichMetadata($dto, $campaign),
            billingAddress: $dto->billingAddress,
            shippingAddress: $dto->shippingAddress,
            notes: $dto->notes,
            currency: $dto->currency ?? ($campaign?->currency ? strtoupper((string) $campaign->currency) : null),
            countryCode: $dto->countryCode,
            discountTotal: $discountTotal,
            taxTotal: $dto->taxTotal,
            shippingTotal: $dto->shippingTotal,
            customerId: $dto->customerId,
            funnelUuid: $dto->funnelUuid,
            funnelSlug: $dto->funnelSlug,
            couponCode: $couponResult['coupon']?->code ?? $dto->couponCode,
            couponId: $couponResult['coupon']?->id ?? $dto->couponId,
        );

        $totals = $this->calculateTotals->execute($enrichedDto, $resolvedItems);

        return new PreparedCheckoutDTO(
            customer: $customer,
            lineItems: $resolvedItems,
            totals: $totals,
            attribution: $attribution,
            campaign: $campaign,
            checkoutDto: $enrichedDto,
        );
    }

    private function mergeCampaignAttribution(OrderAttributionDTO $attribution, $campaign, CreateFunnelOrderDTO $dto): OrderAttributionDTO
    {
        if ($campaign === null) {
            return $attribution;
        }

        $landingPageId = $attribution->landingPageId;

        if ($landingPageId === null && isset($dto->metadata['landing_page_uuid'])) {
            $landingPageId = \App\Models\LandingPage::query()
                ->where('uuid', $dto->metadata['landing_page_uuid'])
                ->value('id');
        }

        return new OrderAttributionDTO(
            funnelId: $attribution->funnelId,
            funnelStepId: $attribution->funnelStepId,
            campaignId: $attribution->campaignId,
            platformCampaignId: $campaign->id,
            landingPageUrl: $attribution->landingPageUrl,
            landingPageId: $landingPageId,
            referrerUrl: $attribution->referrerUrl,
            utmParameters: $attribution->utmParameters,
            landingPageData: $attribution->landingPageData,
            affiliateId: $attribution->affiliateId,
            affiliateClickId: $attribution->affiliateClickId,
            affiliateOfferId: $attribution->affiliateOfferId,
            affiliateSubIds: $attribution->affiliateSubIds,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function enrichMetadata(CreateFunnelOrderDTO $dto, $campaign): array
    {
        $metadata = $dto->metadata;

        if ($campaign !== null) {
            $metadata['campaign_uuid'] = $campaign->uuid;

            if (blank($metadata['subscription_plan_uuid'] ?? null) && $campaign->subscriptionPlan) {
                $metadata['subscription_plan_uuid'] = $campaign->subscriptionPlan->uuid;
            }
        }

        return $metadata;
    }
}
