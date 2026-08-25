<?php

namespace Tests\Unit\LandingPage;

use App\Models\Campaign;
use App\Models\LandingPage;
use App\Modules\LandingPage\Enums\LandingPageStatus;
use App\Modules\LandingPage\Services\LandingPageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_requires_campaign(): void
    {
        $page = LandingPage::factory()->create(['campaign_id' => null]);

        $this->expectException(\InvalidArgumentException::class);
        app(LandingPageService::class)->publish($page);
    }

    public function test_publish_and_public_slug_lookup(): void
    {
        $campaign = Campaign::factory()->create();
        $page = LandingPage::factory()->create(['campaign_id' => $campaign->id]);

        app(LandingPageService::class)->publish($page);

        $found = app(LandingPageService::class)->findPublishedBySlug($page->slug);
        $this->assertNotNull($found);
        $this->assertSame(LandingPageStatus::Published, $found->status);
    }
}
