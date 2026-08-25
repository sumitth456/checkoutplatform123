<?php

namespace App\Modules\Campaign\Services;

use App\Models\Campaign;
use App\Models\CampaignFunnelMapping;
use App\Models\Funnel;
use App\Modules\Campaign\Actions\GenerateCampaignFunnelApiKeyAction;
use App\Modules\Campaign\DTOs\CreateCampaignFunnelMappingDTO;
use App\Modules\Campaign\DTOs\UpdateCampaignFunnelMappingDTO;
use App\Modules\Campaign\Enums\CampaignFunnelMappingStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CampaignFunnelMappingService
{
    public function __construct(
        private readonly GenerateCampaignFunnelApiKeyAction $generateApiKey,
    ) {}

    public function paginate(?int $campaignId = null, int $perPage = 25): LengthAwarePaginator
    {
        return CampaignFunnelMapping::query()
            ->with(['campaign', 'funnel'])
            ->when($campaignId !== null, fn (Builder $query) => $query->where('campaign_id', $campaignId))
            ->orderBy('sort_order')
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * @return array{mapping: CampaignFunnelMapping, api_key: string}
     */
    public function create(Campaign $campaign, CreateCampaignFunnelMappingDTO $dto): array
    {
        return DB::transaction(function () use ($campaign, $dto): array {
            $funnel = Funnel::query()->findOrFail($dto->funnelId);

            if (CampaignFunnelMapping::query()->where('funnel_id', $funnel->id)->exists()) {
                throw new InvalidArgumentException("Funnel [{$funnel->uuid}] is already mapped to a campaign.");
            }

            $key = $this->generateApiKey->execute();
            $sortOrder = $dto->sortOrder > 0
                ? $dto->sortOrder
                : ((int) $campaign->funnelMappings()->max('sort_order')) + 1;

            $mapping = CampaignFunnelMapping::query()->create([
                'campaign_id' => $campaign->id,
                'funnel_id' => $funnel->id,
                'landing_url' => $dto->landingUrl,
                'api_key_encrypted' => $key['plain'],
                'api_key_preview' => $key['preview'],
                'status' => CampaignFunnelMappingStatus::from($dto->status)->value,
                'sort_order' => $sortOrder,
            ]);

            $funnel->update(['platform_campaign_id' => $campaign->id]);

            return [
                'mapping' => $mapping->load(['campaign', 'funnel']),
                'api_key' => $key['plain'],
            ];
        });
    }

    public function update(CampaignFunnelMapping $mapping, UpdateCampaignFunnelMappingDTO $dto): CampaignFunnelMapping
    {
        $mapping->update($dto->toAttributes());

        return $mapping->fresh(['campaign', 'funnel']);
    }

    /**
     * @return array{mapping: CampaignFunnelMapping, api_key: string}
     */
    public function regenerateApiKey(CampaignFunnelMapping $mapping): array
    {
        $key = $this->generateApiKey->execute();

        $mapping->update([
            'api_key_encrypted' => $key['plain'],
            'api_key_preview' => $key['preview'],
        ]);

        return [
            'mapping' => $mapping->fresh(['campaign', 'funnel']),
            'api_key' => $key['plain'],
        ];
    }

    public function delete(CampaignFunnelMapping $mapping): void
    {
        DB::transaction(function () use ($mapping): void {
            Funnel::query()
                ->whereKey($mapping->funnel_id)
                ->where('platform_campaign_id', $mapping->campaign_id)
                ->update(['platform_campaign_id' => null]);

            $mapping->delete();
        });
    }

    public function copyMappings(Campaign $source, Campaign $target): int
    {
        $copied = 0;

        DB::transaction(function () use ($source, $target, &$copied): void {
            foreach ($source->funnelMappings()->with('funnel')->get() as $mapping) {
                if (CampaignFunnelMapping::query()->where('funnel_id', $mapping->funnel_id)->exists()) {
                    continue;
                }

                $key = $this->generateApiKey->execute();

                CampaignFunnelMapping::query()->create([
                    'campaign_id' => $target->id,
                    'funnel_id' => $mapping->funnel_id,
                    'landing_url' => $mapping->landing_url,
                    'api_key_encrypted' => $key['plain'],
                    'api_key_preview' => $key['preview'],
                    'status' => $mapping->status instanceof CampaignFunnelMappingStatus
                        ? $mapping->status->value
                        : (string) $mapping->status,
                    'sort_order' => $mapping->sort_order,
                ]);

                $mapping->funnel?->update(['platform_campaign_id' => $target->id]);
                $copied++;
            }
        });

        return $copied;
    }

    /**
     * Resolve active mapping for checkout using campaign public id/uuid and optional API key.
     */
    public function resolveForCampaignCheckout(?string $campaignIdentifier, ?string $apiKey = null): ?CampaignFunnelMapping
    {
        if (blank($campaignIdentifier)) {
            return null;
        }

        $campaign = Campaign::query()
            ->where('public_id', $campaignIdentifier)
            ->orWhere('uuid', $campaignIdentifier)
            ->first();

        if ($campaign === null) {
            return null;
        }

        $query = CampaignFunnelMapping::query()
            ->with(['campaign', 'funnel'])
            ->where('campaign_id', $campaign->id)
            ->where('status', CampaignFunnelMappingStatus::Active);

        if (filled($apiKey)) {
            $candidates = $query->get();

            foreach ($candidates as $candidate) {
                if ($this->authenticateApiKey($candidate, $apiKey)) {
                    return $candidate;
                }
            }

            return null;
        }

        return $query->orderBy('sort_order')->first();
    }

    public function authenticateApiKey(CampaignFunnelMapping $mapping, string $apiKey): bool
    {
        return hash_equals((string) $mapping->api_key_encrypted, $apiKey);
    }
}
