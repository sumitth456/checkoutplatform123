<?php

/**
 * End-to-end order confirmation email test.
 *
 * Usage (production):
 *   docker compose exec -T app php scripts/test-order-confirmation-email.php
 */

use App\Models\Customer;
use App\Models\EmailLog;
use App\Models\EmailQueueItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Variant;
use App\Modules\Email\Enums\EmailQueueStatus;
use App\Modules\Email\Services\EmailDispatchService;
use App\Modules\Order\Events\OrderPlaced;
use Database\Seeders\EmailModuleSeeder;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../backend/vendor/autoload.php';

$app = require __DIR__.'/../backend/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$email = 'sumitthakur8313@gmail.com';
$firstName = 'Sumit';
$lastName = 'Thakur';

echo "==> Seeding email module\n";
(new EmailModuleSeeder)->run();

$customer = Customer::query()->updateOrCreate(
    ['email' => $email],
    [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone' => '9876543210',
        'status' => 'active',
    ],
);

$product = Product::query()->where('is_active', true)->first()
    ?? Product::query()->first();

if ($product === null) {
    fwrite(STDERR, "No product found. Seed catalog first.\n");
    exit(1);
}

$variant = Variant::query()->where('product_id', $product->id)->first();

$order = Order::query()->create([
    'customer_id' => $customer->id,
    'customer_email' => $email,
    'order_number' => 'E2E-'.now()->format('YmdHis'),
    'status' => 'completed',
    'payment_status' => 'paid',
    'fraud_status' => 'clear',
    'fulfillment_status' => 'unfulfilled',
    'currency' => 'USD',
    'subtotal' => 99.00,
    'tax_total' => 8.91,
    'shipping_total' => 9.99,
    'discount_total' => 0,
    'grand_total' => 117.90,
    'billing_address' => [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'address1' => '123 Test Street',
        'city' => 'Mumbai',
        'state' => 'Maharashtra',
        'postal_code' => '400001',
        'country' => 'IN',
    ],
    'shipping_address' => [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'address1' => '123 Test Street',
        'city' => 'Mumbai',
        'state' => 'Maharashtra',
        'postal_code' => '400001',
        'country' => 'IN',
    ],
    'placed_at' => now(),
    'paid_at' => now(),
]);

$order->items()->create([
    'product_id' => $product->id,
    'variant_id' => $variant?->id,
    'name' => $product->name,
    'sku' => $variant?->sku ?? $product->sku,
    'quantity' => 1,
    'unit_price' => 99.00,
    'total' => 99.00,
    'options' => [
        'color' => 'Blue',
        'size' => 'M',
        'variant' => $variant?->name ?? 'Default',
    ],
]);

echo "==> Dispatching OrderPlaced for {$order->order_number}\n";
event(new OrderPlaced($order->fresh(['items', 'customer'])));

$queueItem = EmailQueueItem::query()
    ->where('recipient_email', $email)
    ->latest('id')
    ->first();

if ($queueItem === null) {
    fwrite(STDERR, "FAIL: No email queue item created.\n");
    exit(1);
}

if ($queueItem->status !== EmailQueueStatus::Sent) {
    echo "==> Processing queue item manually\n";
    app(EmailDispatchService::class)->processQueueItem($queueItem->fresh());
    $queueItem->refresh();
}

$log = EmailLog::query()->where('email_queue_item_id', $queueItem->id)->first();

$checks = [
    'Order created' => $order->exists,
    'Queue item created' => $queueItem !== null,
    'Queue status sent' => $queueItem->status === EmailQueueStatus::Sent,
    'Email log created' => $log !== null,
    'No placeholders in subject' => $log !== null && ! str_contains($log->subject, '{{'),
    'No placeholders in body' => $log !== null && ! str_contains((string) $log->html_body, '{{'),
    'Customer name present' => $log !== null && str_contains((string) $log->html_body, $firstName),
    'Order number present' => $log !== null && str_contains((string) $log->html_body, $order->order_number),
];

$failed = false;

foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL')." — {$label}\n";

    if (! $passed) {
        $failed = true;
    }
}

if ($failed) {
    exit(1);
}

echo "SUCCESS — Order confirmation email sent to {$email}\n";
