<?php

$baseUrl = getenv('ACH_TEST_BASE_URL') ?: 'http://nginx';

function request(string $method, string $url, ?array $payload = null): array
{
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
    ]);

    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $code,
        'body' => json_decode((string) $body, true) ?? [],
        'raw' => (string) $body,
    ];
}

echo "=== ACH E2E Test ===\n";
echo "Base URL: {$baseUrl}\n\n";

// 1. Tokenize
echo "1. Tokenize ACH account...\n";
$tokenize = request('POST', "{$baseUrl}/api/v1/ach/tokenize", [
    'account_holder_name' => 'Sumit Thakur',
    'routing_number' => '110000000',
    'account_number' => '1234567890',
    'account_number_confirmation' => '1234567890',
    'account_type' => 'checking',
    'account_holder_type' => 'personal',
    'customer_email' => 'sumitthakur8313@gmail.com',
]);

echo "   HTTP {$tokenize['code']}\n";

if ($tokenize['code'] !== 201) {
    echo "   FAIL: {$tokenize['raw']}\n";
    exit(1);
}

$paymentToken = $tokenize['body']['data']['payment_token'] ?? null;
echo "   Token: ".substr((string) $paymentToken, 0, 20)."...\n";
echo "   Last4: ".($tokenize['body']['data']['last4'] ?? '?')."\n\n";

// 2. Failed routing
echo "2. Failed routing number test...\n";
$fail = request('POST', "{$baseUrl}/api/v1/ach/tokenize", [
    'account_holder_name' => 'Test User',
    'routing_number' => '000000000',
    'account_number' => '1234567890',
    'account_number_confirmation' => '1234567890',
    'account_type' => 'checking',
]);
echo "   HTTP {$fail['code']} (expected 422)\n";
echo ($fail['code'] === 422 ? "   PASS\n\n" : "   FAIL\n\n");

// 3. Validation
echo "3. Validation test...\n";
$invalid = request('POST', "{$baseUrl}/api/v1/ach/tokenize", [
    'account_holder_name' => 'Test',
    'routing_number' => '12345',
    'account_number' => '1234567890',
    'account_number_confirmation' => '1234567890',
    'account_type' => 'checking',
]);
echo "   HTTP {$invalid['code']} (expected 422)\n";
echo ($invalid['code'] === 422 ? "   PASS\n\n" : "   FAIL\n\n");

// 4. Analytics
echo "4. Analytics endpoint (unauthenticated)...\n";
$analytics = request('GET', "{$baseUrl}/api/v1/analytics/ach");
echo "   HTTP {$analytics['code']} (expected 401 without auth)\n\n";

echo "=== ACH E2E Complete ===\n";
echo "Tokenize: PASS\n";
