<?php

namespace Tests\Unit\Campaign;

use App\Models\Campaign;
use App\Modules\Campaign\Actions\CreateCampaignAction;
use App\Modules\Campaign\Actions\DuplicateCampaignAction;
use App\Modules\Campaign\DTOs\CreateCampaignDTO;
use App\Modules\Campaign\Enums\CampaignStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_campaign_generates_public_id(): void
    {
        $campaign = app(CreateCampaignAction::class)->execute(new CreateCampaignDTO(
            name: 'Summer Funnel',
            currency: 'USD',
            timezone: 'America/New_York',
        ));

        $this->assertMatchesRegularExpression('/^CMP-\d{8}-\d{4}$/', $campaign->public_id);
        $this->assertSame('Summer Funnel', $campaign->name);
        $this->assertSame(CampaignStatus::Draft, $campaign->status);
    }

    public function test_duplicate_campaign_creates_draft_copy(): void
    {
        $original = Campaign::factory()->create([
            'name' => 'Original',
            'status' => CampaignStatus::Active,
        ]);

        $copy = app(DuplicateCampaignAction::class)->execute($original);

        $this->assertNotSame($original->id, $copy->id);
        $this->assertStringContainsString('(Copy)', $copy->name);
        $this->assertSame(CampaignStatus::Draft, $copy->status);
    }
}
