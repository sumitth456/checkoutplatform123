<?php

namespace App\Modules\Order\Support;

use App\Models\Order;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Email;

/**
 * Central customer email validity checks for checkout and post-payment safeguards.
 */
class CustomerEmailValidator
{
    /**
     * Checkout request rules: accept the email string so payment/order completion can proceed.
     * Post-payment validity uses {@see isValid()} (strict + MX).
     *
     * @return list<string>
     */
    public function checkoutRules(): array
    {
        return [
            'required',
            'string',
            'max:255',
        ];
    }

    /**
     * @return list<string|Email>
     */
    public function postPaymentRules(): array
    {
        return [
            'required',
            'string',
            'max:255',
            Email::default()->strict()->validateMxRecord(),
        ];
    }

    public function isValid(?string $email): bool
    {
        if (! filled($email)) {
            return false;
        }

        return Validator::make(
            ['email' => trim((string) $email)],
            ['email' => $this->postPaymentRules()],
        )->passes();
    }

    public function isInvalid(?string $email): bool
    {
        return ! $this->isValid($email);
    }

    public function orderHasInvalidCustomerEmail(Order $order): bool
    {
        if (($order->metadata['invalid_customer_email'] ?? false) === true) {
            return true;
        }

        return $this->isInvalid($order->customer_email);
    }

    public function orderShouldSuppressCustomerOperations(Order $order): bool
    {
        return $this->orderHasInvalidCustomerEmail($order);
    }
}
