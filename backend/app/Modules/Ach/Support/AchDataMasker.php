<?php

namespace App\Modules\Ach\Support;

final class AchDataMasker
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function mask(array $payload): array
    {
        $masked = $payload;

        foreach (['account_number', 'account_number_confirmation', 'routing_number'] as $key) {
            if (! isset($masked[$key])) {
                continue;
            }

            $value = (string) $masked[$key];
            $digits = preg_replace('/\D/', '', $value);

            if ($key === 'routing_number') {
                $masked[$key] = self::maskDigits($digits, 4);
            } else {
                $masked[$key] = self::maskDigits($digits, 4);
            }
        }

        if (isset($masked['payment_token']) && is_string($masked['payment_token'])) {
            $masked['payment_token'] = self::maskToken($masked['payment_token']);
        }

        return $masked;
    }

    public static function maskToken(string $token): string
    {
        if (strlen($token) <= 8) {
            return '****';
        }

        return substr($token, 0, 4).'...'.substr($token, -4);
    }

    private static function maskDigits(string $digits, int $visible): string
    {
        if ($digits === '') {
            return '';
        }

        $last = substr($digits, -$visible);

        return str_repeat('*', max(0, strlen($digits) - $visible)).$last;
    }
}
