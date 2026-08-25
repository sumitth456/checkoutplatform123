<?php

namespace App\Modules\Payment\Contracts;

use App\Modules\Payment\DTOs\PaymentRequest;
use App\Modules\Payment\DTOs\PaymentResponse;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Enums\PaymentStatus;

interface IPaymentGateway
{
    public function provider(): PaymentProvider;

    public function authorize(PaymentRequest $request): PaymentResponse;

    public function capture(string $transactionId, string $amount, string $currency): PaymentResponse;

    public function refund(string $transactionId, string $amount, string $currency): PaymentResponse;

    public function voidTransaction(string $transactionId): PaymentResponse;

    public function getStatus(string $transactionId): PaymentStatus;
}
