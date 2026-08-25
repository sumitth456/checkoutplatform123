<?php

namespace Tests\Unit\StripeWebhook;

use App\Modules\StripeWebhook\Services\StripeEventNormalizerService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StripeEventNormalizerServiceTest extends TestCase
{
    #[Test]
    public function it_maps_stripe_events_to_payment_events(): void
    {
        $event = app(StripeEventNormalizerService::class)->normalize([
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_norm',
                    'status' => 'succeeded',
                    'amount' => 1999,
                    'currency' => 'usd',
                ],
            ],
        ]);

        $this->assertSame('pi_norm', $event->externalId);
        $this->assertSame('19.99', $event->amount);
        $this->assertSame('USD', $event->currency);
        $this->assertSame('captured', $event->type->value);
    }
}
