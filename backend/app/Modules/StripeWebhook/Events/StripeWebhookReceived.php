<?php

namespace App\Modules\StripeWebhook\Events;

use App\Models\StripeWebhookEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StripeWebhookReceived
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly StripeWebhookEvent $webhookEvent,
    ) {}
}
