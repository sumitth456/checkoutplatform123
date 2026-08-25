<?php

namespace App\Modules\Affiliate\Services;

use App\Models\Affiliate;
use App\Models\AffiliateClick;
use App\Models\AffiliateOffer;
use App\Models\Coupon;
use App\Modules\Order\DTOs\OrderAttributionDTO;

class AffiliateAttributionResolver
{
    public function __construct(
        private readonly AffiliateConversionService $conversions,
    ) {}

    public function mergeIntoAttribution(OrderAttributionDTO $attribution, array $metadata, ?string $couponCode = null): OrderAttributionDTO
    {
        if ($attribution->affiliateId !== null) {
            return $attribution;
        }

        $affiliateId = isset($metadata['affiliate_id']) ? (int) $metadata['affiliate_id'] : null;
        $clickId = isset($metadata['affiliate_click_id']) ? (int) $metadata['affiliate_click_id'] : null;
        $offerId = isset($metadata['affiliate_offer_id']) ? (int) $metadata['affiliate_offer_id'] : null;

        if ($affiliateId === null && isset($metadata['affiliate_tracking_id'])) {
            $affiliate = Affiliate::query()
                ->where('tracking_id', $metadata['affiliate_tracking_id'])
                ->where('status', 'active')
                ->first();
            $affiliateId = $affiliate?->id;
        }

        if ($affiliateId === null && isset($metadata['aff'])) {
            $affiliate = Affiliate::query()
                ->where('tracking_id', $metadata['aff'])
                ->where('status', 'active')
                ->first();
            $affiliateId = $affiliate?->id;
        }

        if ($affiliateId === null && $couponCode) {
            $affiliate = $this->conversions->resolveAffiliateFromCoupon($couponCode);
            $affiliateId = $affiliate?->id;
        }

        if ($affiliateId === null) {
            return $attribution;
        }

        if ($clickId === null && isset($metadata['affiliate_click_uuid'])) {
            $clickId = AffiliateClick::query()->where('click_id', $metadata['affiliate_click_uuid'])->value('id');
        }

        if ($offerId === null && isset($metadata['offer'])) {
            $offerId = AffiliateOffer::query()->where('uuid', $metadata['offer'])->value('id')
                ?? (is_numeric($metadata['offer']) ? (int) $metadata['offer'] : null);
        }

        $subIds = array_filter([
            'sub1' => $metadata['sub1'] ?? $metadata['sub_id_1'] ?? null,
            'sub2' => $metadata['sub2'] ?? $metadata['sub_id_2'] ?? null,
            'sub3' => $metadata['sub3'] ?? $metadata['sub_id_3'] ?? null,
            'sub4' => $metadata['sub4'] ?? $metadata['sub_id_4'] ?? null,
            'sub5' => $metadata['sub5'] ?? $metadata['sub_id_5'] ?? null,
        ]);

        return new OrderAttributionDTO(
            funnelId: $attribution->funnelId,
            funnelStepId: $attribution->funnelStepId,
            campaignId: $attribution->campaignId,
            platformCampaignId: $attribution->platformCampaignId,
            landingPageUrl: $attribution->landingPageUrl,
            landingPageId: $attribution->landingPageId,
            referrerUrl: $attribution->referrerUrl,
            utmParameters: $attribution->utmParameters,
            landingPageData: $attribution->landingPageData,
            affiliateId: $affiliateId,
            affiliateClickId: $clickId,
            affiliateOfferId: $offerId,
            affiliateSubIds: $subIds !== [] ? $subIds : null,
        );
    }
}
