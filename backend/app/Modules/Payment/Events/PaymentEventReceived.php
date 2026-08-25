<?php

namespace App\Modules\Payment\Events;

use App\Modules\Payment\DTOs\PaymentEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentEventReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly PaymentEvent $event,
    ) {}
}
