<?php

namespace App\Modules\ShipStation\Support;

final class ShipStationPayloadSanitizer
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'card_number',
        'cardNumber',
        'cvv',
        'cvc',
        'security_code',
        'payment_token',
        'paymentToken',
        'paypal',
        'client_secret',
        'api_secret',
        'api_key',
        'password',
        'labelData',
        'authorization',
        'Authorization',
        'api-key',
        'API-Key',
    ];

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    public static function sanitize(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $sanitized = [];

        foreach ($payload as $key => $value) {
            if (self::isSensitiveKey((string) $key)) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = self::sanitize($value);

                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if ($normalized === strtolower($sensitive) || str_contains($normalized, strtolower($sensitive))) {
                return true;
            }
        }

        return false;
    }
}
