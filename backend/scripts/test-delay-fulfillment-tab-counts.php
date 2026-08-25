<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$counts = app(App\Modules\Admin\Services\AdminDelayFulfillmentService::class)->tabCounts();
$required = ['scheduled', 'due_today', 'overdue', 'in_transit', 'failed', 'cancelled', 'all'];

foreach ($required as $key) {
    if (! array_key_exists($key, $counts)) {
        echo "MISSING key: {$key}\n";
        exit(1);
    }
}

echo "OK tab counts: ".json_encode($counts)."\n";
