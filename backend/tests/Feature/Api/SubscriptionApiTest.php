<?php

namespace Tests\Feature\Api;

use App\Models\Subscription;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_pauses_and_resumes_subscription_via_api(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
            'next_billing_at' => now()->addDays(12),
        ]);

        $pauseResponse = $this->postJson("/api/v1/subscriptions/{$subscription->uuid}/pause");
        $pauseResponse->assertOk()
            ->assertJsonPath('data.status', 'paused')
            ->assertJsonPath('data.paused_remaining_days', 12);

        $resumeResponse = $this->postJson("/api/v1/subscriptions/{$subscription->uuid}/resume");
        $resumeResponse->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    #[Test]
    public function it_cancels_subscription_via_api(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
        ]);

        $response = $this->postJson("/api/v1/subscriptions/{$subscription->uuid}/cancel", [
            'reason' => 'no longer needed',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    #[Test]
    public function it_shows_subscription_with_plan_details(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
        ]);

        $response = $this->getJson("/api/v1/subscriptions/{$subscription->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.uuid', $subscription->uuid)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonStructure([
                'data' => [
                    'uuid',
                    'status',
                    'amount',
                    'currency',
                    'next_bill_date',
                    'plan' => ['uuid', 'name', 'slug'],
                ],
            ]);
    }
}
