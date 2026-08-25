<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

auth()->login(App\Models\User::query()->first());

$html = Livewire\Livewire::test(App\Filament\Admin\Resources\Orders\Pages\ListOrders::class)->html();

echo str_contains($html, 'cp-orders-search-filters') ? "PASS: search panel found\n" : "FAIL: search panel missing\n";
echo str_contains($html, 'Search Orders') ? "PASS: Search Orders button found\n" : "FAIL: Search Orders button missing\n";
echo str_contains($html, 'Order Id') ? "PASS: Order Id field found\n" : "FAIL: Order Id field missing\n";
echo str_contains($html, 'Find Exact Match') ? "PASS: exact match toggles found\n" : "FAIL: exact match toggles missing\n";
