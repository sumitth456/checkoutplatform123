<?php

namespace Tests\Unit\Subscription;

use App\Models\SubscriptionPlanStep;
use App\Modules\Subscription\Enums\BillingCycleType;
use App\Modules\Subscription\Enums\BillingStepType;
use App\Modules\Subscription\Services\BillingScheduleCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillingScheduleCalculatorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_schedules_trial_step_from_days_from_start(): void
    {
        $step = new SubscriptionPlanStep([
            'billing_type' => BillingStepType::OneTime,
            'cycle_type' => BillingCycleType::Trial,
            'days_from_start' => 7,
            'amount' => 0,
        ]);

        $start = Carbon::parse('2026-01-01 12:00:00');
        $next = app(BillingScheduleCalculator::class)->initialBillDate($step, $start);

        $this->assertTrue($next->equalTo($start->copy()->addDays(7)));
    }

    #[Test]
    public function it_advances_monthly_recurring_by_one_month(): void
    {
        $step = new SubscriptionPlanStep([
            'billing_type' => BillingStepType::Recurring,
            'cycle_type' => BillingCycleType::Monthly,
            'interval_days' => 30,
            'amount' => 49.99,
        ]);

        $from = Carbon::parse('2026-01-15');
        $next = app(BillingScheduleCalculator::class)->nextBillDateAfterSuccess($step, $from);

        $this->assertTrue($next->equalTo($from->copy()->addMonth()));
    }
}
