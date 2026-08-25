<?php

namespace Tests\Feature\Api;

use App\Models\StripeWebhookEvent;
use App\Modules\Payment\Enums\LedgerEntryType;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Events\PaymentEventReceived;
use App\Modules\StripeWebhook\Enums\StripeWebhookProcessingStatus;
use App\Modules\StripeWebhook\Jobs\ProcessStripeWebhookJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_normalizes_stripe_webhook_into_internal_event(): void
    {
        Event::fake([PaymentEventReceived::class]);

        $payload = [
            'id' => 'evt_test_webhook_1',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_webhook_1',
                    'status' => 'succeeded',
                    'amount' => 2500,
                    'currency' => 'usd',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/webhooks/stripe', $payload);

        $response->assertOk()
            ->assertJsonPath('received', true)
            ->assertJsonPath('status', StripeWebhookProcessingStatus::Processed->value)
            ->assertJsonPath('event.provider', PaymentProvider::Stripe->value)
            ->assertJsonPath('event.external_id', 'pi_webhook_1')
            ->assertJsonPath('event.type', 'captured');

        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_test_webhook_1',
            'status' => StripeWebhookProcessingStatus::Processed->value,
        ]);

        Event::assertDispatched(PaymentEventReceived::class);

        $this->assertDatabaseHas('payment_ledger_entries', [
            'provider' => PaymentProvider::Stripe->value,
            'entry_type' => LedgerEntryType::Webhook->value,
            'status_to' => PaymentStatus::Captured->value,
            'external_id' => 'pi_webhook_1',
        ]);
    }

    #[Test]
    public function it_rejects_unknown_webhook_provider(): void
    {
        $response = $this->postJson('/api/v1/webhooks/unknown_provider', ['foo' => 'bar']);

        $response->assertNotFound();
    }

    #[Test]
    public function it_deduplicates_stripe_webhook_events(): void
    {
        $payload = [
            'id' => 'evt_duplicate_1',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_dup', 'status' => 'succeeded', 'amount' => 1000, 'currency' => 'usd']],
        ];

        $this->postJson('/api/v1/webhooks/stripe', $payload)->assertOk();
        $second = $this->postJson('/api/v1/webhooks/stripe', $payload);

        $second->assertOk()
            ->assertJsonPath('status', StripeWebhookProcessingStatus::Duplicate->value);

        $this->assertSame(1, StripeWebhookEvent::query()->where('stripe_event_id', 'evt_duplicate_1')->count());
    }

    #[Test]
    public function it_queues_stripe_webhook_processing_job(): void
    {
        Queue::fake();

        $payload = [
            'id' => 'evt_queue_1',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_queue', 'status' => 'succeeded']],
        ];

        $this->postJson('/api/v1/webhooks/stripe', $payload)->assertOk();

        Queue::assertPushed(ProcessStripeWebhookJob::class);
    }
}
