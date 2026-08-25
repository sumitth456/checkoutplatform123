<?php

namespace App\Modules\StripeWebhook\Events;

use App\Models\StripeWebhookEvent;
use App\Modules\Payment\DTOs\PaymentEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StripeWebhookProcessed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly StripeWebhookEvent $webhookEvent,
        public readonly PaymentEvent $paymentEvent,
    ) {}
}
