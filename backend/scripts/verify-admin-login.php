<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Support\Admin\AdminAuthorization;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;

$emails = ['admin@checkoutplatform.test', 'builder@checkoutplatform.test'];

foreach ($emails as $email) {
    $user = User::query()->where('email', $email)->first();
    if ($user === null) {
        echo "{$email}: NOT FOUND\n";
        continue;
    }

    $panel = Filament::getPanel('admin');
    $passOk = Hash::check('password', $user->password);
    $panelOk = $user->canAccessPanel($panel);
    $dashOk = AdminAuthorization::can($user, 'dashboard.view');

    echo "{$email}: status={$user->status} password=".($passOk ? 'OK' : 'FAIL').' panel='.($panelOk ? 'OK' : 'FAIL').' dashboard='.($dashOk ? 'OK' : 'FAIL')."\n";
}
