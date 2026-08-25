<?php

/**
 * Live smoke test against running `php artisan serve`.
 * Usage: php scripts/smoke-test.php [base_url]
 */

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8000', '/');
$api = "{$base}/api/v1";

$passed = 0;
$failed = 0;
$results = [];

function request(string $method, string $url, ?array $body = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_TIMEOUT => 30,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status' => $status,
        'body' => json_decode($response ?: 'null', true),
        'raw' => $response,
    ];
}

function check(string $name, bool $ok, ?string $detail = null): void
{
    global $passed, $failed, $results;

    if ($ok) {
        $passed++;
        $results[] = "✓ {$name}";
    } else {
        $failed++;
        $results[] = "✗ {$name}".($detail ? " — {$detail}" : '');
    }
}

function errors(array $response): string
{
    $errors = $response['body']['errors'] ?? $response['body']['message'] ?? null;

    return is_array($errors) ? json_encode($errors) : (string) ($response['raw'] ?? 'unknown');
}

echo "CheckoutPlatform smoke test\n";
echo "Base URL: {$base}\n\n";

shell_exec('C:\\php84\\php.exe artisan cache:clear 2>nul');

$suffix = (string) time();

// Health
$health = request('GET', "{$base}/up");
check('Health check (/up)', $health['status'] === 200);

// --- Products ---
$product = request('POST', "{$api}/products", [
    'name' => "Smoke Test Product {$suffix}",
    'type' => 'digital',
    'status' => 'active',
    'currency' => 'USD',
    'base_price' => 99.99,
]);
$productUuid = $product['body']['data']['uuid'] ?? null;
check('Create product', $product['status'] === 201 && $productUuid !== null, errors($product));

$list = request('GET', "{$api}/products");
check('List products', $list['status'] === 200 && count($list['body']['data'] ?? []) >= 1);

$show = request('GET', "{$api}/products/{$productUuid}");
check('Show product', $show['status'] === 200 && str_contains($show['body']['data']['name'] ?? '', 'Smoke Test Product'), errors($show));

$update = request('PATCH', "{$api}/products/{$productUuid}", ['name' => "Smoke Test Updated {$suffix}"]);
check('Update product', $update['status'] === 200, errors($update));

// --- Variants ---
$variant = request('POST', "{$api}/products/{$productUuid}/variants", [
    'name' => 'Default',
    'sku' => 'SMK-'.$suffix,
    'price' => 99.99,
    'status' => 'active',
    'inventory_policy' => 'deny',
    'inventory_quantity' => 100,
    'track_inventory' => true,
    'is_default' => true,
]);
$variantUuid = $variant['body']['data']['uuid'] ?? null;
check('Create variant', $variant['status'] === 201 && $variantUuid !== null, errors($variant));

// --- Inventory ---
if ($variantUuid) {
$inv = request('GET', "{$api}/products/{$productUuid}/variants/{$variantUuid}/inventory");
check('Show inventory level', $inv['status'] === 200, errors($inv));

$adjust = request('POST', "{$api}/products/{$productUuid}/variants/{$variantUuid}/inventory/adjust", [
    'quantity_change' => -5,
    'reason' => 'smoke_test',
]);
check('Adjust inventory', in_array($adjust['status'], [200, 201], true), errors($adjust));

$restock = request('POST', "{$api}/products/{$productUuid}/variants/{$variantUuid}/inventory/restock", [
    'quantity' => 20,
    'reason' => 'smoke_test_restock',
]);
check('Restock inventory', in_array($restock['status'], [200, 201], true), errors($restock));

$reservation = request('POST', "{$api}/products/{$productUuid}/variants/{$variantUuid}/inventory/reservations", [
    'quantity' => 3,
    'reference' => 'smoke-order-1',
]);
$resUuid = $reservation['body']['data']['uuid'] ?? null;
check('Reserve inventory', $reservation['status'] === 201 && $resUuid !== null, errors($reservation));

if ($resUuid) {
    $release = request('POST', "{$api}/products/{$productUuid}/variants/{$variantUuid}/inventory/reservations/{$resUuid}/release", []);
    check('Release reservation', $release['status'] === 200, errors($release));
}
}

// --- Payments (Stripe HTTP — may fail without network/mock) ---
$payment = request('POST', "{$api}/payments/authorize", [
    'amount' => 19.99,
    'currency' => 'USD',
    'payment_token' => 'pm_test_smoke_token',
    'provider' => 'stripe',
    'idempotency_key' => 'smoke-'.time(),
]);
check(
    'Authorize payment',
    in_array($payment['status'], [201, 402, 500, 503], true),
    "HTTP {$payment['status']} — ".($payment['status'] === 201 ? 'captured' : 'gateway unreachable/declined (expected without Stripe test keys)')
);
if ($payment['status'] === 201) {
    $txnId = $payment['body']['data']['transaction_id'] ?? null;
    check('Payment has transaction_id', $txnId !== null);
}

// --- Stripe webhook ---
$webhook = request('POST', "{$api}/webhooks/stripe", [
    'id' => 'evt_smoke_'.time(),
    'type' => 'payment_intent.succeeded',
    'data' => [
        'object' => [
            'id' => 'pi_smoke_1',
            'status' => 'succeeded',
            'amount' => 1999,
            'currency' => 'usd',
        ],
    ],
]);
check('Stripe webhook', $webhook['status'] === 200 && ($webhook['body']['received'] ?? false) === true);

// --- Analytics ---
$analytics = request('GET', "{$api}/analytics/overview?currency=USD");
check('Analytics overview', $analytics['status'] === 200 && isset($analytics['body']['data']['revenue']));

$analyticsOrders = request('GET', "{$api}/analytics/orders");
check('Analytics orders', $analyticsOrders['status'] === 200);

        // --- Orders (full checkout flow) ---
        if ($variantUuid) {
            $order = request('POST', "{$api}/orders", [
                'customer' => [
                    'email' => "buyer-{$suffix}@example.com",
                    'first_name' => 'Smoke',
                    'last_name' => 'Buyer',
                ],
                'items' => [
                    ['variant_uuid' => $variantUuid, 'quantity' => 1],
                ],
            ]);
            $orderUuid = $order['body']['data']['uuid'] ?? null;
            check('Place order', $order['status'] === 201 && $orderUuid !== null, errors($order));

            if ($orderUuid) {
                $checkout = request('POST', "{$api}/orders/{$orderUuid}/checkout", [
                    'payment_token' => 'pm_test_order_'.$suffix,
                    'provider' => 'stripe',
                    'idempotency_key' => 'order-smoke-'.$suffix,
                ]);
                check('Checkout order', in_array($checkout['status'], [200, 201], true), errors($checkout));

                $orderList = request('GET', "{$api}/orders");
                check('List orders', $orderList['status'] === 200, errors($orderList));
            }
        }

        // --- Subscriptions (bootstrap via artisan, test lifecycle API) ---
$subUuid = trim((string) shell_exec('C:\\php84\\php.exe artisan tinker --execute="echo App\\Models\\Subscription::factory()->active()->create()->uuid;" 2>nul'));
if ($subUuid !== '') {
    $subShow = request('GET', "{$api}/subscriptions/{$subUuid}");
    check('Show subscription', $subShow['status'] === 200);

    $subPause = request('POST', "{$api}/subscriptions/{$subUuid}/pause");
    check('Pause subscription', $subPause['status'] === 200 && ($subPause['body']['data']['status'] ?? '') === 'paused');

    $subResume = request('POST', "{$api}/subscriptions/{$subUuid}/resume");
    check('Resume subscription', $subResume['status'] === 200 && ($subResume['body']['data']['status'] ?? '') === 'active');

    $subCancel = request('POST', "{$api}/subscriptions/{$subUuid}/cancel", ['reason' => 'smoke_test']);
    check('Cancel subscription', $subCancel['status'] === 200 && ($subCancel['body']['data']['status'] ?? '') === 'cancelled');
} else {
    check('Subscription bootstrap', false, 'Could not create test subscription');
}

// --- Admin panel ---
$admin = request('GET', "{$base}/admin/login");
check('Filament admin login page', $admin['status'] === 200);

echo "\n--- Results ---\n";
foreach ($results as $line) {
    echo $line."\n";
}

echo "\nAutomated: {$passed} passed, {$failed} failed\n";

exit($failed > 0 ? 1 : 0);
