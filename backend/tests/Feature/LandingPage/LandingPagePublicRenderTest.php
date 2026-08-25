<?php

namespace Tests\Feature\LandingPage;

use App\Models\Campaign;
use App\Models\LandingPage;
use App\Modules\LandingPage\Services\LandingPageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPagePublicRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_page_renders_at_offer_slug(): void
    {
        $campaign = Campaign::factory()->create();
        $page = LandingPage::factory()->create([
            'slug' => 'tshirt-offer',
            'campaign_id' => $campaign->id,
            'content_html' => '<h1>Summer Tee</h1>',
        ]);

        app(LandingPageService::class)->publish($page);

        $this->get('/offer/tshirt-offer')
            ->assertOk()
            ->assertSee('Summer Tee');
    }

    public function test_draft_page_returns_404(): void
    {
        LandingPage::factory()->create(['slug' => 'hidden-offer']);

        $this->get('/offer/hidden-offer')->assertNotFound();
    }
}
