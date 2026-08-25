<?php

namespace Tests\Unit\LandingPage;

use App\Models\LandingPage;
use App\Modules\Analytics\Data\AnalyticsPeriod;
use App\Modules\LandingPage\Services\LandingPageAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LandingPageAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_records_conversion_on_landing_page(): void
    {
        $page = LandingPage::factory()->create(['conversion_count' => 0]);

        app(LandingPageAnalyticsService::class)->recordConversion($page, 9.99);

        $page->refresh();
        $this->assertSame(1, (int) $page->conversion_count);
        $this->assertSame('9.99', (string) $page->revenue_total);
    }

    #[Test]
    public function it_returns_empty_snapshot_when_no_views(): void
    {
        $period = new AnalyticsPeriod(Carbon::now()->startOfMonth(), Carbon::now());
        $snapshot = app(LandingPageAnalyticsService::class)->snapshot($period);

        $this->assertSame(0, $snapshot['views']);
        $this->assertSame(0, $snapshot['conversions']);
    }
}
