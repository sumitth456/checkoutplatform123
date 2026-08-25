<?php

namespace Tests\Unit\LandingPage;

use App\Modules\LandingPage\Forms\LandingPageCheckoutFormRenderer;
use Tests\TestCase;

class LandingPageCheckoutFormDetectionTest extends TestCase
{
    public function test_detects_legacy_konnektive_form(): void
    {
        $renderer = app(LandingPageCheckoutFormRenderer::class);

        $html = '<form id="order_form" method="post"><input name="email" /></form>';

        $this->assertTrue($renderer->pageContainsCheckoutForm($html));
    }

    public function test_detects_any_form_tag(): void
    {
        $renderer = app(LandingPageCheckoutFormRenderer::class);

        $this->assertTrue($renderer->pageContainsCheckoutForm('<form class="signup"><input /></form>'));
        $this->assertFalse($renderer->pageContainsCheckoutForm('<section>No form here</section>'));
    }
}
