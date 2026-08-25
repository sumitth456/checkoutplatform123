<?php

namespace Tests\Unit\LandingPage;

use App\Models\LandingPage;
use App\Modules\LandingPage\Forms\CheckoutFormFieldRegistry;
use App\Modules\LandingPage\Forms\LandingPageCheckoutFormRenderer;
use App\Modules\LandingPage\Services\LandingPageFormService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageCheckoutFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_default_checkout_form(): void
    {
        $page = LandingPage::factory()->create();

        $form = app(LandingPageFormService::class)->ensureDefaultCheckoutForm($page);

        $this->assertSame('checkout-form', $form->form_key);
        $this->assertCount(count(CheckoutFormFieldRegistry::defaultCheckoutFields()), $form->field_schema);
    }

    public function test_renderer_outputs_checkout_form_id(): void
    {
        $page = LandingPage::factory()->create();
        $form = app(LandingPageFormService::class)->ensureDefaultCheckoutForm($page);

        $html = app(LandingPageCheckoutFormRenderer::class)->render($form);

        $this->assertStringContainsString('id="checkout-form"', $html);
        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('name="variant_uuid"', $html);
    }

    public function test_inject_form_into_page_html(): void
    {
        $page = LandingPage::factory()->create(['content_html' => '<section>Offer</section>']);

        app(LandingPageFormService::class)->injectFormIntoPageHtml($page);

        $page->refresh();
        $this->assertStringContainsString('id="checkout-form"', (string) $page->content_html);
    }
}
