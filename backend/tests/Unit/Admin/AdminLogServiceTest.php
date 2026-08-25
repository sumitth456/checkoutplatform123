<?php

namespace Tests\Unit\Admin;

use App\Modules\Admin\Services\AdminLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminLogServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_zero_counts_when_no_failures_or_webhooks_exist(): void
    {
        $service = app(AdminLogService::class);

        $this->assertSame(['all' => 0, 'stripe' => 0], $service->paymentFailureTabCounts());
        $this->assertSame(
            ['all' => 0, 'pending' => 0, 'processed' => 0, 'failed' => 0],
            $service->webhookEventTabCounts(),
        );
    }
}
