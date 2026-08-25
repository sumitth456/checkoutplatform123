<?php

/**
 * Create a fresh admin user with full permissions (including landing pages).
 *
 * Usage: php scripts/create-admin-user.php [email] [password]
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = $argv[1] ?? 'builder@checkoutplatform.test';
$password = $argv[2] ?? 'password';

$seeder = new Database\Seeders\AdminPanelPermissionSeeder;
$seeder->run();

$adminRole = Spatie\Permission\Models\Role::query()
    ->where('name', 'Admin')
    ->where('guard_name', 'web')
    ->first();

if ($adminRole === null) {
    echo "Admin role not found. Run AdminPanelPermissionSeeder.\n";
    exit(1);
}

$user = App\Models\User::withTrashed()->updateOrCreate(
    ['email' => $email],
    [
        'name' => 'Landing Page Builder',
        'first_name' => 'Landing',
        'last_name' => 'Builder',
        'password' => Illuminate\Support\Facades\Hash::make($password),
        'status' => 'active',
        'email_verified_at' => now(),
    ],
);

if ($user->trashed()) {
    $user->restore();
}

if (blank($user->uuid)) {
    $user->forceFill(['uuid' => (string) Illuminate\Support\Str::uuid()])->save();
}

$user->syncRoles([$adminRole]);

$canUpdate = App\Support\Admin\AdminAuthorization::can($user, 'landing_pages.update');

echo "User created/updated successfully.\n";
echo "Email: {$email}\n";
echo "Password: {$password}\n";
echo 'landing_pages.update: '.($canUpdate ? 'YES' : 'NO')."\n";
echo "Login: ".url('/admin/login')."\n";
