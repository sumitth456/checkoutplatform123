<?php

namespace App\Modules\Payment\Contracts;

use App\Modules\Payment\DTOs\PaymentEvent;
use App\Modules\Payment\Enums\PaymentProvider;
use Illuminate\Http\Request;

interface IWebhookHandler
{
    public function provider(): PaymentProvider;

    public function handle(Request $request): PaymentEvent;
}
