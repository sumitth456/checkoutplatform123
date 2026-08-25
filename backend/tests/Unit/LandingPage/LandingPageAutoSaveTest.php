<?php

namespace Tests\Unit\LandingPage;

use App\Models\LandingPage;
use App\Modules\LandingPage\Services\LandingPageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LandingPageAutoSaveTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_auto_saves_builder_json_and_compiles_html(): void
    {
        $page = LandingPage::factory()->create([
            'content_html' => '<section>Old</section>',
        ]);

        $document = [
            'version' => 1,
            'settings' => [],
            'nodes' => [
                [
                    'id' => 'hero',
                    'type' => 'heading',
                    'props' => ['text' => 'Auto-saved draft', 'level' => 'h2'],
                    'styles' => ['desktop' => [], 'tablet' => [], 'mobile' => []],
                ],
            ],
        ];

        $updated = app(LandingPageService::class)->autoSave($page, [
            'builder_json' => $document,
        ]);

        $this->assertSame('Auto-saved draft', $updated->builder_json['nodes'][0]['props']['text']);
        $this->assertStringContainsString('Auto-saved draft', (string) $updated->content_html);
        $this->assertNotNull($updated->last_auto_saved_at);
    }
}
