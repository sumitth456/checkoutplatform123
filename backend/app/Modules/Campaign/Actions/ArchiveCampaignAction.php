<?php

namespace App\Modules\Campaign\Actions;

use App\Models\Campaign;
use App\Modules\Campaign\Enums\CampaignStatus;
use App\Modules\Campaign\Contracts\CampaignRepositoryInterface;

final class ArchiveCampaignAction
{
    public function __construct(
        private readonly CampaignRepositoryInterface $campaigns,
    ) {}

    public function execute(Campaign $campaign): Campaign
    {
        return $this->campaigns->update($campaign, [
            'status' => CampaignStatus::Archived,
            'archived_at' => now(),
        ]);
    }
}
