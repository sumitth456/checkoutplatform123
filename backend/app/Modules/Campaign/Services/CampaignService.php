<?php

namespace App\Modules\Campaign\Services;

use App\Models\Campaign;
use App\Modules\Campaign\Actions\ArchiveCampaignAction;
use App\Modules\Campaign\Actions\CreateCampaignAction;
use App\Modules\Campaign\Actions\DuplicateCampaignAction;
use App\Modules\Campaign\Actions\UpdateCampaignAction;
use App\Modules\Campaign\Contracts\CampaignRepositoryInterface;
use App\Modules\Campaign\DTOs\CreateCampaignDTO;
use App\Modules\Campaign\DTOs\UpdateCampaignDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class CampaignService
{
    public function __construct(
        private readonly CampaignRepositoryInterface $campaigns,
        private readonly CreateCampaignAction $createCampaign,
        private readonly UpdateCampaignAction $updateCampaign,
        private readonly DuplicateCampaignAction $duplicateCampaign,
        private readonly ArchiveCampaignAction $archiveCampaign,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->campaigns->paginate($filters, $perPage);
    }

    public function findByUuid(string $uuid): ?Campaign
    {
        return $this->campaigns->findByUuid($uuid);
    }

    public function create(CreateCampaignDTO $dto): Campaign
    {
        return $this->createCampaign->execute($dto);
    }

    public function update(Campaign $campaign, UpdateCampaignDTO $dto): Campaign
    {
        return $this->updateCampaign->execute($campaign, $dto);
    }

    public function duplicate(Campaign $campaign): Campaign
    {
        return $this->duplicateCampaign->execute($campaign);
    }

    public function archive(Campaign $campaign): Campaign
    {
        return $this->archiveCampaign->execute($campaign);
    }

    public function delete(Campaign $campaign): void
    {
        $this->campaigns->delete($campaign);
    }

    /**
     * @return array{all: int, active: int, draft: int, paused: int, archived: int}
     */
    public function statusCounts(): array
    {
        return $this->campaigns->statusCounts();
    }
}
