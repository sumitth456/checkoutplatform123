<?php

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$order = App\Models\Order::query()->where('payment_method_type', 'ach')->latest('id')->first();
echo json_encode($order?->only(['uuid', 'order_number', 'payment_method_type', 'payment_status', 'grand_total', 'customer_email']), JSON_PRETTY_PRINT) . "\n";

$tx = $order?->transactions()->latest('id')->first();
echo json_encode($tx?->only(['uuid', 'authorization_id', 'external_id', 'payment_method_type', 'payment_method_last4', 'payment_method_brand', 'status']), JSON_PRETTY_PRINT) . "\n";
