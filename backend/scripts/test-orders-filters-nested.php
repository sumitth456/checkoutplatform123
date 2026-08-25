<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

auth()->login(App\Models\User::query()->first());

$html = Livewire\Livewire::test(App\Filament\Admin\Resources\Orders\Pages\ListOrders::class)->html();

$datePos = strpos($html, 'orders-date-filters');
$searchPos = strpos($html, 'orders-search-filters');

echo "date_pos={$datePos}\n";
echo "search_pos={$searchPos}\n";
echo "nested=".($searchPos > $datePos && $searchPos < $datePos + 15000 ? 'yes' : 'no')."\n";

$snippet = substr($html, $datePos, 800);
echo "\n--- snippet ---\n{$snippet}\n";
