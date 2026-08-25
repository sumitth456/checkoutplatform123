<?php

namespace App\Modules\Campaign\Contracts;

use App\Models\Campaign;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CampaignRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?Campaign;

    public function findByPublicId(string $publicId): ?Campaign;

    public function create(array $attributes): Campaign;

    public function update(Campaign $campaign, array $attributes): Campaign;

    public function delete(Campaign $campaign): void;

    /**
     * @return array{all: int, active: int, draft: int, paused: int, archived: int}
     */
    public function statusCounts(): array;
}
