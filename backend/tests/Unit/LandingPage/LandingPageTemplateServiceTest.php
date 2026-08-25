<?php

namespace Tests\Unit\LandingPage;

use App\Models\Campaign;
use App\Models\LandingPage;
use App\Models\LandingPageForm;
use App\Models\LandingPageTemplate;
use App\Modules\LandingPage\Builder\LandingPageBuilderDocument;
use App\Modules\LandingPage\Services\LandingPageTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_from_page_snapshots_builder_content_and_forms(): void
    {
        $page = LandingPage::factory()->create([
            'name' => 'Test Landing Page',
            'content_html' => '<section>Hero</section>',
            'content_css' => '.hero { color: red; }',
            'content_js' => 'console.log("ok");',
            'custom_header_scripts' => '<meta name="test" content="1">',
            'custom_footer_scripts' => '<script>track();</script>',
            'builder_json' => [
                'version' => 1,
                'settings' => ['customCss' => '.hero { color: red; }'],
                'nodes' => [
                    ['id' => 'n1', 'type' => 'section', 'props' => [], 'children' => []],
                ],
            ],
        ]);

        LandingPageForm::query()->create([
            'landing_page_id' => $page->id,
            'name' => 'Checkout',
            'form_key' => 'checkout-form',
            'field_schema' => [['name' => 'email', 'type' => 'email']],
            'settings' => ['auto_inject' => true],
            'is_active' => true,
        ]);

        $template = app(LandingPageTemplateService::class)->createFromPage(
            $page->fresh(['forms', 'assets']),
            'Test Landing Page Template',
            ['category' => 'Sales'],
        );

        $this->assertSame('Test Landing Page Template', $template->name);
        $this->assertSame('Sales', $template->category);
        $this->assertNotEmpty($template->builder_json['nodes'] ?? []);
        $this->assertNotEmpty($template->content_html);
        $this->assertCount(1, $template->metadata['forms'] ?? []);
        $this->assertSame($page->id, $template->source_landing_page_id);
    }

    public function test_create_page_from_template_is_independent_copy(): void
    {
        $campaign = Campaign::factory()->create();

        $source = LandingPage::factory()->create([
            'name' => 'Test Landing Page',
            'campaign_id' => $campaign->id,
            'builder_json' => [
                'version' => 1,
                'settings' => [],
                'nodes' => [
                    ['id' => 'hero', 'type' => 'heading', 'props' => ['text' => 'Original'], 'children' => []],
                ],
            ],
            'content_html' => '<h1>Original</h1>',
        ]);

        LandingPageForm::query()->create([
            'landing_page_id' => $source->id,
            'name' => 'Checkout',
            'form_key' => 'checkout-form',
            'field_schema' => [['name' => 'email', 'type' => 'email']],
            'settings' => [],
            'is_active' => true,
        ]);

        $template = app(LandingPageTemplateService::class)->createFromPage(
            $source->fresh(['forms', 'assets']),
            'Test Template',
        );

        $copy = app(LandingPageTemplateService::class)->createPageFromTemplate(
            $template,
            [
                'name' => 'Test Landing Page Copy',
                'campaign_id' => $campaign->id,
            ],
        );

        $this->assertNotSame($source->id, $copy->id);
        $this->assertSame('Test Landing Page Copy', $copy->name);
        $this->assertSame($template->id, $copy->template_id);
        $this->assertStringContainsString('Original', (string) $copy->content_html);
        $this->assertCount(1, $copy->forms);

        $copy->update(['content_html' => '<h1>Modified Copy</h1>']);
        $template->refresh();

        $this->assertStringContainsString('Original', (string) $template->content_html);
        $this->assertStringNotContainsString('Modified Copy', (string) $template->content_html);
    }

    public function test_create_page_from_template_preserves_legacy_html_when_builder_json_is_sparse(): void
    {
        $campaign = Campaign::factory()->create();

        $legacyHtml = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />'
            .'<form id="order_form"><input name="email" /></form>'
            .str_repeat('<section class="hero">Imported legacy block</section>', 200);

        $template = LandingPageTemplate::query()->create([
            'name' => 'Legacy Template',
            'slug' => 'legacy-template',
            'content_html' => $legacyHtml,
            'content_css' => '.hero { color: red; }',
            'builder_json' => [
                'version' => 1,
                'settings' => [],
                'nodes' => [
                    ['id' => 'n1', 'type' => 'heading', 'props' => ['text' => 'Your headline'], 'children' => []],
                    ['id' => 'n2', 'type' => 'container', 'props' => [], 'children' => []],
                ],
            ],
            'metadata' => ['forms' => [], 'assets' => []],
            'is_active' => true,
        ]);

        $copy = app(LandingPageTemplateService::class)->createPageFromTemplate(
            $template,
            [
                'name' => 'Legacy Clone',
                'campaign_id' => $campaign->id,
            ],
        );

        $this->assertSame($legacyHtml, $copy->content_html);
        $this->assertStringContainsString('order_form', (string) $copy->content_html);
        $this->assertGreaterThan(10000, strlen((string) $copy->content_html));
        $this->assertNotSame($template->getKey(), $copy->getKey());
    }

    public function test_duplicate_template_creates_new_record(): void
    {
        $template = LandingPageTemplate::query()->create([
            'name' => 'Base Template',
            'slug' => 'base-template',
            'content_html' => '<div>Base</div>',
            'builder_json' => LandingPageBuilderDocument::normalize([]),
            'is_active' => true,
        ]);

        $duplicate = app(LandingPageTemplateService::class)->duplicate($template);

        $this->assertNotSame($template->id, $duplicate->id);
        $this->assertSame('Base Template Copy', $duplicate->name);
        $this->assertNotSame($template->slug, $duplicate->slug);
    }

    public function test_update_from_page_replaces_template_content(): void
    {
        $page = LandingPage::factory()->create([
            'content_html' => '<p>Updated content</p>',
            'builder_json' => [
                'version' => 1,
                'settings' => [],
                'nodes' => [
                    ['id' => 'n1', 'type' => 'text', 'props' => ['text' => 'Updated'], 'children' => []],
                ],
            ],
        ]);

        $template = LandingPageTemplate::query()->create([
            'name' => 'Stale Template',
            'slug' => 'stale-template',
            'content_html' => '<p>Old</p>',
            'builder_json' => LandingPageBuilderDocument::normalize([]),
            'is_active' => true,
        ]);

        app(LandingPageTemplateService::class)->updateFromPage($template, $page->fresh(['forms', 'assets']));

        $template->refresh();

        $this->assertNotEmpty($template->builder_json['nodes'] ?? []);
        $this->assertStringContainsString('Updated', (string) $template->content_html);
        $this->assertSame($page->id, $template->source_landing_page_id);
    }
}
