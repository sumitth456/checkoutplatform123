<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\TransactionPolicy;
use Database\Seeders\AdminPanelPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TransactionPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_role_can_view_and_export_transactions(): void
    {
        $this->seed(AdminPanelPermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Admin');

        $policy = new TransactionPolicy;

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->export($user));
    }

    public function test_admin_cannot_create_or_mutate_transactions(): void
    {
        $this->seed(AdminPanelPermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Admin');

        $policy = new TransactionPolicy;

        $this->assertFalse($policy->create($user));
    }

    public function test_user_without_permissions_cannot_view_transactions(): void
    {
        $this->seed(AdminPanelPermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['status' => 'active']);

        $policy = new TransactionPolicy;

        $this->assertFalse($policy->viewAny($user));
    }
}
