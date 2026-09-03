<?php

namespace Tests\Feature\LandingPage;

use App\Models\Campaign;
use App\Models\LandingPage;
use App\Models\LandingPageSetting;
use App\Models\Product;
use App\Models\Variant;
use App\Modules\LandingPage\Enums\LandingPageStatus;
use App\Modules\LandingPage\Http\Controllers\LandingPagePublicController;
use App\Modules\LandingPage\Services\LandingPageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TshirtLandingPageHostnameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'landing_pages.tshirt.domain' => 'famteesworld.com',
            'landing_pages.tshirt.use_https' => true,
        ]);

        LandingPageSetting::set('tshirt_domain', 'famteesworld.com');

        Route::middleware(['web'])
            ->domain('{tshirtSlug}.famteesworld.com')
            ->where(['tshirtSlug' => '[a-z0-9]+(?:-[a-z0-9]+)*'])
            ->group(function (): void {
                Route::get('/', [LandingPagePublicController::class, 'showByHostname']);
            });
    }

    #[Test]
    public function published_tshirt_page_renders_on_subdomain_hostname(): void
    {
        $campaign = Campaign::factory()->create();
        $page = LandingPage::factory()->create([
            'slug' => 'jamescook',
            'is_tshirt_campaign' => true,
            'campaign_id' => $campaign->id,
            'content_html' => '<h1>James Cook T-Shirt</h1>',
        ]);

        app(LandingPageService::class)->publish($page);

        $this->get('http://jamescook.famteesworld.com/')
            ->assertOk()
            ->assertSee('James Cook T-Shirt');
    }

    #[Test]
    public function another_slug_renders_on_its_subdomain(): void
    {
        $campaign = Campaign::factory()->create();
        $page = LandingPage::factory()->create([
            'slug' => 'seahawks',
            'is_tshirt_campaign' => true,
            'campaign_id' => $campaign->id,
            'content_html' => '<h1>Seahawks T-Shirt</h1>',
        ]);

        app(LandingPageService::class)->publish($page);

        $this->get('http://seahawks.famteesworld.com/')
            ->assertOk()
            ->assertSee('Seahawks T-Shirt');
    }

    #[Test]
    public function unknown_subdomain_returns_not_found(): void
    {
        $this->get('http://missing-campaign.famteesworld.com/')->assertNotFound();
    }

    #[Test]
    public function draft_tshirt_page_is_not_public_on_subdomain(): void
    {
        $campaign = Campaign::factory()->create();
        LandingPage::factory()->create([
            'slug' => 'draft-page',
            'is_tshirt_campaign' => true,
            'campaign_id' => $campaign->id,
            'status' => LandingPageStatus::Draft,
            'content_html' => '<h1>Draft</h1>',
        ]);

        $this->get('http://draft-page.famteesworld.com/')->assertNotFound();
    }

    #[Test]
    public function non_tshirt_page_is_not_served_on_subdomain(): void
    {
        $campaign = Campaign::factory()->create();
        $page = LandingPage::factory()->create([
            'slug' => 'standard-offer',
            'is_tshirt_campaign' => false,
            'campaign_id' => $campaign->id,
            'content_html' => '<h1>Standard Offer</h1>',
        ]);

        app(LandingPageService::class)->publish($page);

        $this->get('http://standard-offer.famteesworld.com/')->assertNotFound();
        $this->get('/offer/standard-offer')->assertOk()->assertSee('Standard Offer');
    }

    #[Test]
    public function reserved_www_subdomain_does_not_resolve_to_landing_page(): void
    {
        $campaign = Campaign::factory()->create();
        LandingPage::factory()->create([
            'slug' => 'www',
            'is_tshirt_campaign' => true,
            'campaign_id' => $campaign->id,
            'status' => LandingPageStatus::Published,
            'published_at' => now(),
            'content_html' => '<h1>Should Not Show</h1>',
        ]);

        $this->get('http://www.famteesworld.com/')->assertNotFound();
    }

    #[Test]
    public function external_domain_does_not_resolve_landing_page(): void
    {
        $campaign = Campaign::factory()->create();
        $page = LandingPage::factory()->create([
            'slug' => 'jamescook',
            'is_tshirt_campaign' => true,
            'campaign_id' => $campaign->id,
            'content_html' => '<h1>James Cook</h1>',
        ]);

        app(LandingPageService::class)->publish($page);

        $response = $this->get('http://jamescook.example.com/');

        $response->assertDontSee('James Cook');
    }

    #[Test]
    public function landing_page_can_map_existing_product_and_variant(): void
    {
        $product = Product::factory()->create(['name' => 'Mens T- JamesCookWhite - Medium', 'sku' => 'G5000-PARENT']);
        $variant = Variant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'G5000-White-MD',
            'name' => 'Medium',
        ]);

        $page = LandingPage::factory()->create([
            'is_tshirt_campaign' => true,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
        ]);

        $page->refresh();

        $this->assertSame($product->id, $page->product_id);
        $this->assertSame('G5000-White-MD', $page->variant?->sku);
        $this->assertSame('https://'.$page->slug.'.famteesworld.com', $page->publicUrl());
    }
}
