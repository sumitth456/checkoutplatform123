<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));
auth()->login(App\Models\User::query()->first());

try {
    Livewire\Livewire::test(App\Filament\Admin\Resources\DelayFulfillment\Pages\ListDelayFulfillment::class);
    echo "OK delay-fulfillment page loads\n";
} catch (Throwable $e) {
    echo 'FAIL '.$e->getMessage()."\n";
    exit(1);
}
