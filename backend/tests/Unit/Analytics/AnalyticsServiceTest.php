<?php

namespace Tests\Unit\Analytics;

use App\Models\Subscription;
use App\Modules\Analytics\Data\AnalyticsPeriod;
use App\Modules\Analytics\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_normalizes_weekly_subscription_to_monthly_mrr(): void
    {
        $plan = \App\Models\SubscriptionPlan::factory()->checkoutChampTrialContinuity()->create();
        $plan->steps()->updateOrCreate(
            ['step_order' => 2],
            [
                'billing_type' => 'recurring',
                'interval_days' => 7,
                'amount' => 7.00,
                'currency' => 'USD',
            ],
        );

        Subscription::factory()->active()->create([
            'subscription_plan_id' => $plan->id,
            'current_cycle_step' => 2,
            'amount' => 7.00,
        ]);

        $mrr = app(AnalyticsService::class)->mrr(new AnalyticsPeriod(now()->startOfMonth(), now()));

        $this->assertSame('30.00', $mrr->mrr);
    }
}
