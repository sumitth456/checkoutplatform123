<?php

namespace App\Modules\ShipStation\Support;

final class ShipStationApiErrorFormatter
{
    /**
     * @param  array<string, mixed>  $body
     */
    public static function format(int $status, array $body): string
    {
        $message = self::extractMessage($body);

        if ($status === 401 && self::isPlanOrFeatureRestriction($message, $body)) {
            return 'ShipStation plan or feature restriction: '.self::sanitize($message ?: 'This endpoint is not available on your current plan.');
        }

        if ($status === 401 && self::isInvalidCredentials($message, $body)) {
            return 'ShipStation API authentication failed.';
        }

        if ($status === 401 && filled($message)) {
            return self::sanitize($message);
        }

        if ($status === 401) {
            return 'ShipStation API authentication failed.';
        }

        if ($status === 403) {
            return filled($message)
                ? self::sanitize($message)
                : 'ShipStation API access forbidden.';
        }

        if ($status === 429) {
            return 'ShipStation API rate limit exceeded.';
        }

        if (filled($message)) {
            return self::sanitize($message);
        }

        return "ShipStation API request failed with HTTP {$status}.";
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function extractMessage(array $body): string
    {
        $errors = $body['errors'] ?? null;

        if (is_array($errors) && $errors !== []) {
            $messages = collect($errors)
                ->map(fn (mixed $error): string => is_array($error)
                    ? (string) ($error['message'] ?? json_encode($error))
                    : (string) $error)
                ->filter()
                ->values()
                ->all();

            if ($messages !== []) {
                return implode(' | ', $messages);
            }
        }

        return (string) ($body['message'] ?? $body['Message'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function isPlanOrFeatureRestriction(string $message, array $body): bool
    {
        $haystack = strtolower($message.' '.json_encode($body['errors'] ?? []));

        foreach (['billing plan', 'upgrade your', 'required features', 'not available on your', 'plan or feature'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function isInvalidCredentials(string $message, array $body): bool
    {
        if (self::isPlanOrFeatureRestriction($message, $body)) {
            return false;
        }

        $haystack = strtolower($message.' '.json_encode($body['errors'] ?? []));

        foreach (['invalid api key', 'invalid key', 'expired key', 'deactivated', 'unauthorized', 'authentication failed'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return blank($message);
    }

    public static function sanitize(string $message): string
    {
        return (string) preg_replace('/[A-Za-z0-9]{32,}/', '[REDACTED]', trim($message));
    }
}
