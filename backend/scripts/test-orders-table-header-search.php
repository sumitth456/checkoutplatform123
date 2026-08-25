<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

auth()->login(App\Models\User::query()->first());

$html = Livewire\Livewire::test(App\Filament\Admin\Resources\Orders\Pages\ListOrders::class)->html();

$checks = [
    'Search orders' => str_contains($html, 'Search orders'),
    'Order Id field' => str_contains($html, 'Order Id'),
    'fi-ta-header-ctn' => str_contains($html, 'fi-ta-header-ctn'),
    'search in table header' => str_contains($html, 'fi-ta-header-ctn') && str_contains($html, 'cp-orders-search-filters'),
];

foreach ($checks as $label => $pass) {
    echo ($pass ? 'PASS' : 'FAIL').": {$label}\n";
}

$headerPos = strpos($html, 'fi-ta-header-ctn');
$searchPos = strpos($html, 'cp-orders-search-filters');
if ($headerPos !== false && $searchPos !== false) {
    echo 'search_after_header='.(($searchPos > $headerPos) ? 'yes' : 'no')."\n";
}
