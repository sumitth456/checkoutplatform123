<?php

namespace App\Modules\Order\Actions;

use App\Models\Campaign;
use App\Modules\Campaign\Enums\CampaignStatus;
use App\Modules\Order\DTOs\CreateFunnelOrderDTO;

final class ResolveCampaignCheckoutAction
{
    public function execute(CreateFunnelOrderDTO $dto): ?Campaign
    {
        $uuid = $dto->metadata['campaign_uuid'] ?? null;

        if (blank($uuid)) {
            return null;
        }

        return Campaign::query()
            ->where('uuid', (string) $uuid)
            ->whereIn('status', [CampaignStatus::Active, CampaignStatus::Draft])
            ->with('subscriptionPlan')
            ->first();
    }
}
