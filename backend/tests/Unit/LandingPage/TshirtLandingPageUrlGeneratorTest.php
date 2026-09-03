<?php

namespace Tests\Unit\LandingPage;

use App\Models\LandingPage;
use App\Modules\LandingPage\Services\TshirtLandingPageSettingsService;
use App\Modules\LandingPage\Services\TshirtLandingPageUrlGenerator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TshirtLandingPageUrlGeneratorTest extends TestCase
{
    #[Test]
    public function it_generates_https_subdomain_url_from_slug_and_global_domain(): void
    {
        config([
            'landing_pages.tshirt.domain' => 'famteesworld.com',
            'landing_pages.tshirt.use_https' => true,
        ]);

        $generator = app(TshirtLandingPageUrlGenerator::class);

        $this->assertSame('https://jamescook.famteesworld.com', $generator->forSlug('jamescook'));
        $this->assertSame('https://seahawks.famteesworld.com', $generator->forSlug('seahawks'));
    }

    #[Test]
    public function it_generates_url_for_tshirt_landing_page_record(): void
    {
        config(['landing_pages.tshirt.domain' => 'famteesworld.com']);

        $page = new LandingPage([
            'slug' => 'campaign123',
            'is_tshirt_campaign' => true,
        ]);

        $this->assertSame(
            'https://campaign123.famteesworld.com',
            app(TshirtLandingPageUrlGenerator::class)->forLandingPage($page),
        );
    }

    #[Test]
    public function settings_service_extracts_slug_from_hostname(): void
    {
        config(['landing_pages.tshirt.domain' => 'famteesworld.com']);

        $settings = app(TshirtLandingPageSettingsService::class);

        $this->assertSame('jamescook', $settings->extractSlugFromHost('jamescook.famteesworld.com'));
        $this->assertNull($settings->extractSlugFromHost('www.famteesworld.com'));
        $this->assertNull($settings->extractSlugFromHost('famteesworld.com'));
        $this->assertNull($settings->extractSlugFromHost('evil.example.com'));
    }
}
