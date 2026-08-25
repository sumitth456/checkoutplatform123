<?php

namespace App\Modules\Ach\Support;

final class AchValidator
{
    /**
     * @return array<string, string>
     */
    public static function validateBankDetails(array $data): array
    {
        $errors = [];

        $holderName = trim((string) ($data['account_holder_name'] ?? ''));
        if ($holderName === '') {
            $errors['account_holder_name'] = 'Account holder name is required.';
        } elseif (strlen($holderName) > 255) {
            $errors['account_holder_name'] = 'Account holder name is too long.';
        }

        $routing = preg_replace('/\D/', '', (string) ($data['routing_number'] ?? ''));
        if ($routing === '') {
            $errors['routing_number'] = 'Routing number is required.';
        } elseif (strlen($routing) !== 9) {
            $errors['routing_number'] = 'Routing number must be exactly 9 digits.';
        }

        $account = preg_replace('/\D/', '', (string) ($data['account_number'] ?? ''));
        $confirm = preg_replace('/\D/', '', (string) ($data['account_number_confirmation'] ?? ''));

        if ($account === '') {
            $errors['account_number'] = 'Account number is required.';
        } elseif (strlen($account) < 4 || strlen($account) > 17) {
            $errors['account_number'] = 'Account number must be between 4 and 17 digits.';
        }

        if ($confirm === '') {
            $errors['account_number_confirmation'] = 'Please confirm your account number.';
        } elseif ($account !== $confirm) {
            $errors['account_number_confirmation'] = 'Account numbers do not match.';
        }

        $accountType = (string) ($data['account_type'] ?? '');
        if (! in_array($accountType, ['checking', 'savings'], true)) {
            $errors['account_type'] = 'Please select a valid account type.';
        }

        $holderType = (string) ($data['account_holder_type'] ?? 'personal');
        if (! in_array($holderType, ['personal', 'business'], true)) {
            $errors['account_holder_type'] = 'Please select a valid account holder type.';
        }

        return $errors;
    }

    public static function normalizeRoutingNumber(string $value): string
    {
        return substr(preg_replace('/\D/', '', $value), 0, 9);
    }

    public static function normalizeAccountNumber(string $value): string
    {
        return substr(preg_replace('/\D/', '', $value), 0, 17);
    }

    public static function last4(string $accountNumber): string
    {
        $digits = preg_replace('/\D/', '', $accountNumber);

        return substr($digits, -4);
    }
}
