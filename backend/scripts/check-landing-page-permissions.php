<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (App\Models\User::query()->get() as $user) {
    $roles = $user->getRoleNames()->implode(', ');
    $update = App\Support\Admin\AdminAuthorization::can($user, 'landing_pages.update') ? 'YES' : 'NO';
    $view = App\Support\Admin\AdminAuthorization::can($user, 'landing_pages.view') ? 'YES' : 'NO';
    $direct = method_exists($user, 'checkPermissionTo') && $user->checkPermissionTo('landing_pages.update') ? 'YES' : 'NO';
    echo "{$user->email}\troles={$roles}\tview={$view}\tupdate={$update}\tdirect_perm={$direct}\n";
}

$permExists = Spatie\Permission\Models\Permission::query()
    ->where('name', 'landing_pages.update')
    ->exists();
echo "\npermission landing_pages.update exists in DB: ".($permExists ? 'YES' : 'NO')."\n";

$landing = Spatie\Permission\Models\Permission::query()
    ->where('name', 'like', 'landing_pages.%')
    ->pluck('name')
    ->all();
echo 'landing_pages permissions in DB: '.(count($landing) ? implode(', ', $landing) : '(none)')."\n";
echo 'total permissions: '.Spatie\Permission\Models\Permission::count()."\n";
