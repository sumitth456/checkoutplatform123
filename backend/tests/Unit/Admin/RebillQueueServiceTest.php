<?php

namespace Tests\Unit\Admin;

use App\Models\Customer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Modules\Admin\Services\RebillQueueService;
use App\Modules\Subscription\Enums\RebillQueueStatus;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RebillQueueServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_skip_charge_updates_queue_state(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
            'rebill_queue_status' => RebillQueueStatus::Queued,
        ]);

        $until = now()->addDays(14);

        app(RebillQueueService::class)->skipCharge($subscription, $until);

        $subscription->refresh();

        $this->assertSame(RebillQueueStatus::Skipped, $subscription->rebill_queue_status);
        $this->assertSame($until->toDateTimeString(), $subscription->next_billing_at->toDateTimeString());
    }

    public function test_due_now_query_includes_past_due_billing_date(): void
    {
        Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
            'rebill_queue_status' => RebillQueueStatus::Queued,
            'next_billing_at' => now()->subHour(),
        ]);

        Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
            'rebill_queue_status' => RebillQueueStatus::Queued,
            'next_billing_at' => now()->addWeek(),
        ]);

        $this->assertSame(1, app(RebillQueueService::class)->dueNowQuery()->count());
    }
}
