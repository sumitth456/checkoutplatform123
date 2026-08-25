<?php

namespace Tests\Unit\Campaign;

use App\Models\Campaign;
use App\Models\CampaignFunnelMapping;
use App\Modules\Campaign\Services\CampaignFunnelMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CampaignFunnelMappingServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_paginates_mappings_for_campaign(): void
    {
        $campaign = Campaign::factory()->create();
        CampaignFunnelMapping::query()->create([
            'campaign_id' => $campaign->id,
            'funnel_id' => \App\Models\Funnel::factory()->create()->id,
            'landing_url' => 'https://example.com/offer',
            'api_key_encrypted' => 'test-key',
            'api_key_preview' => 'test',
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $paginator = app(CampaignFunnelMappingService::class)->paginate($campaign->id);

        $this->assertSame(1, $paginator->total());
    }
}
