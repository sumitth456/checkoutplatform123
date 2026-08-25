<?php

namespace App\Modules\Payment\Contracts;

use App\Modules\Ach\DTOs\AchTokenizeRequest;
use App\Modules\Ach\DTOs\AchTokenizeResponse;
use App\Modules\Payment\DTOs\PaymentResponse;

/**
 * Extended payment gateway contract supporting tokenization and verification.
 * ACH providers implement this interface; card gateways continue using IPaymentGateway.
 */
interface PaymentGatewayInterface extends IPaymentGateway
{
    public function tokenize(AchTokenizeRequest $request): AchTokenizeResponse;

    public function verify(string $transactionId): PaymentResponse;
}
