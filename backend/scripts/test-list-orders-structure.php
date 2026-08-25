<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

auth()->login(App\Models\User::query()->first());

$html = Livewire\Livewire::test(App\Filament\Admin\Resources\Orders\Pages\ListOrders::class)->html();

$markers = [
    'fi-page-header',
    'cp-orders-date-filters',
    'All orders',
    'fi-ta-table',
    'Export orders',
];

foreach ($markers as $marker) {
    $pos = strpos($html, $marker);
    echo $marker.': '.($pos === false ? 'MISSING' : $pos)."\n";
}

$start = strpos($html, 'fi-page-content');
if ($start !== false) {
    echo "\n--- fi-page-content excerpt ---\n";
    echo substr($html, $start, 3500);
}
