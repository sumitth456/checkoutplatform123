<?php

namespace Tests\Feature\Subscription;

use App\Models\DunningPolicy;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Events\SubscriptionDunningTriggered;
use App\Modules\Subscription\Services\DunningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DunningServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_schedules_dunning_stage_after_failure(): void
    {
        $dunning = DunningPolicy::factory()->create();
        $plan = SubscriptionPlan::factory()->create([
            'dunning_policy_id' => $dunning->id,
        ]);

        $subscription = Subscription::factory()->create([
            'subscription_plan_id' => $plan->id,
            'status' => SubscriptionStatus::PastDue,
            'dunning_stage' => 0,
        ]);

        app(DunningService::class)->schedule($subscription->fresh(['plan.dunningPolicy']));

        $subscription->refresh();

        $this->assertSame(1, $subscription->dunning_stage);
        $this->assertNotNull($subscription->next_dunning_at);
        $this->assertDatabaseHas('subscription_dunning_events', [
            'subscription_id' => $subscription->id,
            'stage' => 1,
        ]);
    }

    #[Test]
    public function it_dispatches_event_when_processing_due_dunning(): void
    {
        Event::fake([SubscriptionDunningTriggered::class]);

        $dunning = DunningPolicy::factory()->create();
        $plan = SubscriptionPlan::factory()->create(['dunning_policy_id' => $dunning->id]);
        $subscription = Subscription::factory()->create([
            'subscription_plan_id' => $plan->id,
            'status' => SubscriptionStatus::PastDue,
            'dunning_stage' => 1,
            'next_dunning_at' => now()->subMinute(),
        ]);

        $subscription->dunningEvents()->create([
            'stage' => 1,
            'channel' => 'email',
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinute(),
        ]);

        app(DunningService::class)->processStage($subscription->fresh());

        Event::assertDispatched(SubscriptionDunningTriggered::class);
    }
}
