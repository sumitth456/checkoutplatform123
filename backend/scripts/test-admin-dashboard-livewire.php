<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));
auth()->login(App\Models\User::where('email', 'admin@checkoutplatform.test')->first());

try {
    $test = Livewire\Livewire::test(App\Filament\Admin\Pages\AdminDashboard::class);
    $html = $test->html();
    echo 'OK html len '.strlen($html)."\n";
    foreach (['Operations Dashboard', 'Quick range', 'Key metrics', 'Total Orders', 'Revenue Trend', 'Sales Summary', 'wire:model.live="quickRange"'] as $needle) {
        echo $needle.': '.(str_contains($html, $needle) ? 'yes' : 'no')."\n";
    }

    $test->set('quickRange', 'today')->call('applyFilters');
    echo "After today filter: OK\n";
} catch (Throwable $e) {
    echo 'FAIL '.$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n";
}
