<?php

namespace App\Modules\Order\Actions;

use App\Models\Funnel;
use App\Modules\Order\DTOs\CreateFunnelOrderDTO;
use App\Modules\Order\DTOs\OrderAttributionDTO;

class ResolveFunnelAction
{
    public function execute(CreateFunnelOrderDTO $dto): OrderAttributionDTO
    {
        $attribution = $dto->attribution;

        if ($attribution->funnelId !== null) {
            return $attribution;
        }

        $funnel = null;

        if ($dto->funnelUuid !== null) {
            $funnel = Funnel::query()->where('uuid', $dto->funnelUuid)->first();
        }

        if ($funnel === null && $dto->funnelSlug !== null) {
            $funnel = Funnel::query()->where('slug', $dto->funnelSlug)->first();
        }

        if ($funnel === null) {
            return $attribution;
        }

        return new OrderAttributionDTO(
            funnelId: $funnel->id,
            funnelStepId: $attribution->funnelStepId,
            campaignId: $attribution->campaignId,
            platformCampaignId: $attribution->platformCampaignId,
            landingPageUrl: $attribution->landingPageUrl,
            landingPageId: $attribution->landingPageId,
            referrerUrl: $attribution->referrerUrl,
            utmParameters: $attribution->utmParameters,
            landingPageData: $attribution->landingPageData,
        );
    }
}
