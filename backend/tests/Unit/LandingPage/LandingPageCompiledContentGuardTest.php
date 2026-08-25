<?php

namespace Tests\Unit\LandingPage;

use App\Modules\LandingPage\Support\LandingPageCompiledContentGuard;
use Tests\TestCase;

class LandingPageCompiledContentGuardTest extends TestCase
{
    public function test_uses_compiled_html_when_stored_content_is_empty(): void
    {
        $this->assertTrue(
            LandingPageCompiledContentGuard::shouldUseCompiledHtml('', '<h1>Compiled</h1>'),
        );
    }

    public function test_preserves_legacy_html_when_compiled_output_is_tiny(): void
    {
        $legacy = '<link href="https://cdn.example/bootstrap.css" rel="stylesheet" />'
            .str_repeat('<section>Legacy block</section>', 500);
        $compiled = '<h2 class="lp-node">Your headline</h2>';

        $this->assertFalse(
            LandingPageCompiledContentGuard::shouldUseCompiledHtml($legacy, $compiled),
        );
    }

    public function test_uses_compiled_html_for_visual_builder_pages(): void
    {
        $stored = '<h2 class="lp-node">Old headline</h2>';
        $compiled = '<h2 class="lp-node">Updated headline</h2>';

        $this->assertTrue(
            LandingPageCompiledContentGuard::shouldUseCompiledHtml($stored, $compiled),
        );
    }
}
