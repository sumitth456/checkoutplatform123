<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$html = view('filament.admin.resources.orders.list-records', [
    'this' => app(\App\Filament\Admin\Resources\Orders\Pages\ListOrders::class),
])->render();

echo str_contains($html, 'Quick range') ? "PASS: Quick range in template\n" : "FAIL: missing Quick range\n";
echo 'ListOrders view property: '.(new ReflectionClass(\App\Filament\Admin\Resources\Orders\Pages\ListOrders::class))->getProperty('view')->getDefaultValue()."\n";
