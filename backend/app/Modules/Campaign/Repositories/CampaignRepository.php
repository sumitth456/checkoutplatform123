<?php

namespace App\Modules\Campaign\Repositories;

use App\Models\Campaign;
use App\Modules\Campaign\Contracts\CampaignRepositoryInterface;
use App\Modules\Campaign\Enums\CampaignStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class CampaignRepository implements CampaignRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Campaign::query()->with('brand');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('public_id', 'like', "%{$search}%")
                    ->orWhere('store_name', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['brand_id'])) {
            $query->where('brand_id', (int) $filters['brand_id']);
        }

        return $query->latest()->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?Campaign
    {
        return Campaign::query()->where('uuid', $uuid)->first();
    }

    public function findByPublicId(string $publicId): ?Campaign
    {
        return Campaign::query()->where('public_id', $publicId)->first();
    }

    public function create(array $attributes): Campaign
    {
        return Campaign::query()->create($attributes);
    }

    public function update(Campaign $campaign, array $attributes): Campaign
    {
        $campaign->update($attributes);

        return $campaign->fresh();
    }

    public function delete(Campaign $campaign): void
    {
        $campaign->delete();
    }

    public function statusCounts(): array
    {
        return [
            'all' => Campaign::query()->count(),
            'active' => Campaign::query()->where('status', CampaignStatus::Active)->count(),
            'draft' => Campaign::query()->where('status', CampaignStatus::Draft)->count(),
            'paused' => Campaign::query()->where('status', CampaignStatus::Paused)->count(),
            'archived' => Campaign::query()->where('status', CampaignStatus::Archived)->count(),
        ];
    }
}
