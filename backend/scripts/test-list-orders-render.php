<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::query()->first();
if ($user === null) {
    echo "NO_USER\n";
    exit(1);
}

auth()->login($user);

$html = Livewire\Livewire::test(App\Filament\Admin\Resources\Orders\Pages\ListOrders::class)->html();

echo str_contains($html, 'Quick range') ? "PASS: Quick range found\n" : "FAIL: Quick range missing\n";
echo str_contains($html, 'list-records') ? "has list-records ref\n" : "";
echo str_contains($html, 'cp-orders-date-filters') ? "PASS: filter class found\n" : "FAIL: filter class missing\n";
echo 'html_length='.strlen($html)."\n";
