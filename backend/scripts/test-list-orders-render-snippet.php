<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

auth()->login(App\Models\User::query()->first());

$html = Livewire\Livewire::test(App\Filament\Admin\Resources\Orders\Pages\ListOrders::class)->html();

$pos = strpos($html, 'cp-orders-date-filters');
if ($pos === false) {
    echo "NOT FOUND\n";
    exit(1);
}

echo substr($html, max(0, $pos - 200), 800)."\n";
