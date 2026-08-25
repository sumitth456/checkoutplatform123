<?php

namespace Tests\Unit\Subscription;

use App\Models\Subscription;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Events\SubscriptionCancelled;
use App\Modules\Subscription\Events\SubscriptionPaused;
use App\Modules\Subscription\Events\SubscriptionResumed;
use App\Modules\Subscription\Services\SubscriptionLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubscriptionLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_pauses_and_resumes_with_remaining_days_preserved(): void
    {
        Event::fake([SubscriptionPaused::class, SubscriptionResumed::class]);

        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
            'next_billing_at' => now()->addDays(10),
        ]);

        $lifecycle = app(SubscriptionLifecycleService::class);
        $lifecycle->pause($subscription);

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Paused, $subscription->status);
        $this->assertSame(10, $subscription->paused_remaining_days);

        $lifecycle->resume($subscription);

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertNull($subscription->paused_remaining_days);
        $this->assertTrue($subscription->next_billing_at->greaterThan(now()->addDays(9)));

        Event::assertDispatched(SubscriptionPaused::class);
        Event::assertDispatched(SubscriptionResumed::class);
    }

    #[Test]
    public function it_cancels_subscription_and_dispatches_event(): void
    {
        Event::fake([SubscriptionCancelled::class]);

        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
        ]);

        app(SubscriptionLifecycleService::class)->cancel($subscription, 'customer_request');

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->status);
        $this->assertSame('customer_request', $subscription->cancellation_reason);

        Event::assertDispatched(SubscriptionCancelled::class);
    }
}
