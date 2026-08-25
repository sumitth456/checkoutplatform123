<?php

namespace App\Modules\Payment\Contracts;

use App\Modules\Payment\DTOs\PaymentRequest;
use App\Modules\Payment\Enums\PaymentProvider;

interface GatewayRoutingStrategyInterface
{
    public function resolve(PaymentRequest $request, ?PaymentProvider $preferred = null): PaymentProvider;
}
