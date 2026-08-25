<?php

namespace App\Modules\Campaign\Actions;

use App\Models\Campaign;
use App\Modules\Campaign\Enums\CampaignStatus;
use App\Modules\Campaign\Contracts\CampaignRepositoryInterface;
use App\Modules\Campaign\Services\CampaignMerchandisingService;
use App\Modules\Campaign\Services\CampaignProductBulkService;
use Illuminate\Support\Facades\DB;

final class DuplicateCampaignAction
{
    public function __construct(
        private readonly CampaignRepositoryInterface $campaigns,
        private readonly GenerateCampaignPublicIdAction $generatePublicId,
        private readonly CampaignProductBulkService $campaignProducts,
        private readonly CampaignMerchandisingService $merchandising,
    ) {}

    public function execute(Campaign $campaign): Campaign
    {
        return DB::transaction(function () use ($campaign): Campaign {
            $copy = $this->campaigns->create([
                'public_id' => $this->generatePublicId->execute(),
                'name' => $campaign->name.' (Copy)',
                'description' => $campaign->description,
                'internal_notes' => $campaign->internal_notes,
                'status' => CampaignStatus::Draft->value,
                'brand_id' => $campaign->brand_id,
                'store_name' => $campaign->store_name,
                'store_code' => $campaign->store_code,
                'currency' => $campaign->currency,
                'timezone' => $campaign->timezone,
                'metadata' => $campaign->metadata,
                'archived_at' => null,
            ]);

            $this->campaignProducts->copyProducts($campaign, $copy);
            $campaign->load(['upsells', 'downsells', 'orderBumps']);
            $this->merchandising->copyMerchandising($campaign, $copy);

            return $copy;
        });
    }
}
