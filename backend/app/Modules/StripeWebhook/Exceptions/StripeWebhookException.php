<?php

namespace App\Modules\StripeWebhook\Exceptions;

use RuntimeException;

class StripeWebhookException extends RuntimeException
{
    public static function invalidSignature(): self
    {
        return new self('Invalid Stripe webhook signature.');
    }

    public static function invalidPayload(string $message): self
    {
        return new self("Invalid Stripe webhook payload: {$message}");
    }

    public static function eventNotFound(int $id): self
    {
        return new self("Stripe webhook event [{$id}] was not found.");
    }
}
