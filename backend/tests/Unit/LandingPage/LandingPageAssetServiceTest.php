<?php

namespace Tests\Unit\LandingPage;

use App\Models\LandingPage;
use App\Models\LandingPageAsset;
use App\Modules\LandingPage\Enums\LandingPageAssetType;
use App\Modules\LandingPage\Services\LandingPageAssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class LandingPageAssetServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_infer_type_from_mime(): void
    {
        $service = app(LandingPageAssetService::class);

        $this->assertSame(LandingPageAssetType::Video, $service->inferTypeFromMime('video/mp4', 'clip.mp4'));
        $this->assertSame(LandingPageAssetType::Css, $service->inferTypeFromMime('text/css', 'theme.css'));
        $this->assertSame(LandingPageAssetType::Font, $service->inferTypeFromMime('font/woff2', 'inter.woff2'));
    }

    public function test_embed_snippet_for_image(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('landing-pages/assets/image/test.jpg', 'bytes');

        $page = LandingPage::factory()->create();
        $asset = LandingPageAsset::factory()->create([
            'landing_page_id' => $page->id,
            'path' => 'landing-pages/assets/image/test.jpg',
            'filename' => 'hero.jpg',
            'alt_text' => 'Hero',
        ]);

        $snippet = app(LandingPageAssetService::class)->embedSnippet($asset);

        $this->assertStringContainsString('<img src=', $snippet);
        $this->assertStringContainsString('alt="Hero"', $snippet);
    }

    public function test_library_includes_global_and_page_assets(): void
    {
        $page = LandingPage::factory()->create();
        LandingPageAsset::factory()->create(['landing_page_id' => null]);
        LandingPageAsset::factory()->create(['landing_page_id' => $page->id]);
        LandingPageAsset::factory()->create(['landing_page_id' => LandingPage::factory()->create()->id]);

        $library = app(LandingPageAssetService::class)->libraryForPage($page);

        $this->assertCount(2, $library);
    }

    public function test_protected_delivery_requires_signature_or_referer(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('landing-pages/assets/video/v.mp4', 'video');

        $asset = LandingPageAsset::factory()->protected()->create([
            'type' => LandingPageAssetType::Video,
            'path' => 'landing-pages/assets/video/v.mp4',
            'mime_type' => 'video/mp4',
        ]);

        $this->get(route('landing-pages.assets.deliver', $asset))->assertForbidden();

        URL::forceRootUrl('http://localhost');
        $signed = URL::temporarySignedRoute('landing-pages.assets.deliver', now()->addHour(), ['asset' => $asset->uuid]);

        $this->get($signed)->assertOk();
    }
}
