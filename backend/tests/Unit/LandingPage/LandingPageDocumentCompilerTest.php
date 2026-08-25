<?php

namespace Tests\Unit\LandingPage;

use App\Modules\LandingPage\Builder\LandingPageBuilderDocument;
use App\Modules\LandingPage\Builder\LandingPageDocumentCompiler;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LandingPageDocumentCompilerTest extends TestCase
{
    #[Test]
    public function it_compiles_heading_and_button_nodes_to_html(): void
    {
        $document = LandingPageBuilderDocument::normalize([
            'nodes' => [
                [
                    'id' => 'node_heading',
                    'type' => 'heading',
                    'props' => ['text' => 'Buy now', 'level' => 'h1'],
                    'styles' => ['desktop' => [], 'tablet' => [], 'mobile' => []],
                ],
                [
                    'id' => 'node_button',
                    'type' => 'button',
                    'props' => ['text' => 'Checkout', 'url' => '#checkout-form', 'target' => '_self'],
                    'styles' => ['desktop' => [], 'tablet' => [], 'mobile' => []],
                ],
            ],
        ]);

        $compiled = app(LandingPageDocumentCompiler::class)->compile($document);

        $this->assertStringContainsString('<h1 class="lp-node lp-node_heading">Buy now</h1>', $compiled['html']);
        $this->assertStringContainsString('href="#checkout-form"', $compiled['html']);
        $this->assertStringContainsString('Checkout', $compiled['html']);
        $this->assertStringContainsString('.lp-node_heading{', $compiled['css']);
    }

    #[Test]
    public function it_compiles_nested_layout_containers(): void
    {
        $document = LandingPageBuilderDocument::normalize([
            'nodes' => [
                [
                    'id' => 'sec1',
                    'type' => 'section',
                    'props' => [],
                    'styles' => ['desktop' => [], 'tablet' => [], 'mobile' => []],
                    'children' => [
                        [
                            'id' => 'txt1',
                            'type' => 'text',
                            'props' => ['text' => 'Hello world'],
                            'styles' => ['desktop' => [], 'tablet' => [], 'mobile' => []],
                        ],
                    ],
                ],
            ],
        ]);

        $compiled = app(LandingPageDocumentCompiler::class)->compile($document);

        $this->assertStringContainsString('lp-sec1', $compiled['html']);
        $this->assertStringContainsString('Hello world', $compiled['html']);
    }

    #[Test]
    public function it_hides_nodes_when_visibility_is_hidden(): void
    {
        $document = LandingPageBuilderDocument::normalize([
            'nodes' => [
                [
                    'id' => 'hidden_text',
                    'type' => 'text',
                    'props' => ['text' => 'Hidden'],
                    'styles' => [
                        'desktop' => ['visibility' => 'hidden'],
                        'tablet' => [],
                        'mobile' => [],
                    ],
                ],
            ],
        ]);

        $compiled = app(LandingPageDocumentCompiler::class)->compile($document);

        $this->assertStringContainsString('display:none!important', $compiled['css']);
    }
}
