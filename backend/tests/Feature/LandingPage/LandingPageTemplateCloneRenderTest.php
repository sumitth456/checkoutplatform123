<?php

namespace Tests\Feature\LandingPage;

use App\Models\Campaign;
use App\Models\LandingPageTemplate;
use App\Modules\LandingPage\Services\LandingPageService;
use App\Modules\LandingPage\Services\LandingPageTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTemplateCloneRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_clone_renders_full_legacy_template_html(): void
    {
        $campaign = Campaign::factory()->create();

        $legacyHtml = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />'
            .'<form id="order_form"><input name="email" value="test@example.com" /></form>'
            .'<section class="hero">Imported legacy hero</section>';

        $template = LandingPageTemplate::query()->create([
            'name' => 'Legacy Template',
            'slug' => 'legacy-template-render',
            'content_html' => $legacyHtml,
            'content_css' => '.hero { background: #111; }',
            'builder_json' => [
                'version' => 1,
                'settings' => [],
                'nodes' => [
                    ['id' => 'n1', 'type' => 'heading', 'props' => ['text' => 'Your headline'], 'children' => []],
                ],
            ],
            'metadata' => ['forms' => [], 'assets' => []],
            'is_active' => true,
        ]);

        $page = app(LandingPageTemplateService::class)->createPageFromTemplate($template, [
            'name' => 'Legacy Published Clone',
            'slug' => 'legacy-published-clone',
            'campaign_id' => $campaign->id,
        ]);

        app(LandingPageService::class)->publish($page->fresh());

        $this->get('/offer/legacy-published-clone')
            ->assertOk()
            ->assertSee('Imported legacy hero', false)
            ->assertSee('order_form', false)
            ->assertSee('bootstrap.min.css', false);
    }
}
