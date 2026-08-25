<?php

namespace Tests\Unit\Payment;

use App\Modules\Payment\Enums\PaymentEventType;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Events\PaymentEventReceived;
use App\Modules\Payment\Services\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebhookDispatcherTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_dispatches_paypal_webhook_and_records_ledger(): void
    {
        Event::fake([PaymentEventReceived::class]);

        $request = Request::create('/webhooks/paypal', 'POST', [
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => [
                'id' => 'paypal_refund_1',
                'status' => 'COMPLETED',
                'amount' => ['value' => '5.00', 'currency_code' => 'USD'],
            ],
        ]);

        $event = app(WebhookDispatcher::class)->dispatch(PaymentProvider::PayPal, $request);

        $this->assertSame(PaymentEventType::Refunded, $event->type);
        $this->assertSame('paypal_refund_1', $event->externalId);
        $this->assertSame(PaymentProvider::PayPal, $event->provider);

        Event::assertDispatched(PaymentEventReceived::class);

        $this->assertDatabaseHas('payment_ledger_entries', [
            'provider' => PaymentProvider::PayPal->value,
            'external_id' => 'paypal_refund_1',
            'status_to' => PaymentStatus::Refunded->value,
        ]);
    }
}
