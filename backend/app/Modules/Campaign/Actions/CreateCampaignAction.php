<?php

namespace App\Modules\Campaign\Actions;

use App\Models\Campaign;
use App\Modules\Campaign\DTOs\CreateCampaignDTO;
use App\Modules\Campaign\Contracts\CampaignRepositoryInterface;

final class CreateCampaignAction
{
    public function __construct(
        private readonly CampaignRepositoryInterface $campaigns,
        private readonly GenerateCampaignPublicIdAction $generatePublicId,
    ) {}

    public function execute(CreateCampaignDTO $dto): Campaign
    {
        $publicId = $this->generatePublicId->execute();

        return $this->campaigns->create($dto->toAttributes($publicId));
    }
}
