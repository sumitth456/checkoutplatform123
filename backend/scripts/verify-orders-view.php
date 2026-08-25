<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$page = new App\Filament\Admin\Resources\Orders\Pages\ListOrders();
echo 'view='.$page->getView().PHP_EOL;
echo 'blade_exists='.(view()->exists('filament.admin.resources.orders.list-records') ? 'yes' : 'no').PHP_EOL;
echo 'filters_exists='.(view()->exists('filament.admin.resources.orders.date-filters') ? 'yes' : 'no').PHP_EOL;
