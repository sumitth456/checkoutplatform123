<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::withTrashed()->updateOrCreate(
            ['email' => 'admin@checkoutplatform.test'],
            [
                'name' => 'Admin User',
                'first_name' => 'Admin',
                'last_name' => 'User',
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        if ($admin->trashed()) {
            $admin->restore();
        }

        if (blank($admin->uuid)) {
            $admin->forceFill(['uuid' => (string) Str::uuid()])->save();
        }

        $this->call(AdminPanelPermissionSeeder::class);
        $this->call(EmailModuleSeeder::class);

        if (! app()->environment('production')) {
            $this->call(ProductSeeder::class);
        }
    }
}
