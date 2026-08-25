<?php

namespace App\Modules\Campaign\Actions;

use App\Models\Campaign;
use App\Modules\Campaign\DTOs\UpdateCampaignDTO;
use App\Modules\Campaign\Enums\CampaignStatus;
use App\Modules\Campaign\Contracts\CampaignRepositoryInterface;

final class UpdateCampaignAction
{
    public function __construct(
        private readonly CampaignRepositoryInterface $campaigns,
    ) {}

    public function execute(Campaign $campaign, UpdateCampaignDTO $dto): Campaign
    {
        $attributes = $dto->toAttributes();

        if (($attributes['status'] ?? '') === CampaignStatus::Archived->value) {
            $attributes['archived_at'] = $campaign->archived_at ?? now();
        } elseif ($campaign->status === CampaignStatus::Archived && ($attributes['status'] ?? '') !== CampaignStatus::Archived->value) {
            $attributes['archived_at'] = null;
        }

        return $this->campaigns->update($campaign, $attributes);
    }
}
