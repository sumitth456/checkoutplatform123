<?php

namespace App\Support\Admin;

final class AddressFormatter
{
    /**
     * @param  array<string, mixed>|string|null  $address
     */
    public static function format(mixed $address): string
    {
        if ($address === null || $address === '') {
            return '—';
        }

        if (is_string($address)) {
            $decoded = json_decode($address, true);

            if (is_array($decoded)) {
                return self::formatArray($decoded);
            }

            $trimmed = trim($address);

            return $trimmed !== '' ? $trimmed : '—';
        }

        if (! is_array($address) || $address === []) {
            return '—';
        }

        return self::formatArray($address);
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private static function formatArray(array $address): string
    {
        $lines = array_filter([
            trim(($address['first_name'] ?? '').' '.($address['last_name'] ?? '')),
            $address['company'] ?? null,
            $address['line1'] ?? null,
            $address['line2'] ?? null,
            trim(
                ($address['city'] ?? '').
                (filled($address['state'] ?? null) ? ', '.$address['state'] : '').
                ' '.($address['postal_code'] ?? '')
            ),
            strtoupper((string) ($address['country'] ?? '')),
            $address['phone'] ?? null,
        ], fn (?string $line): bool => filled($line));

        return $lines === [] ? '—' : implode("\n", $lines);
    }
}
