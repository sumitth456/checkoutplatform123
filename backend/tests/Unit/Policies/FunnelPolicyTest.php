<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\FunnelPolicy;
use Database\Seeders\AdminPanelPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FunnelPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_role_can_manage_funnels(): void
    {
        $this->seed(AdminPanelPermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Admin');

        $policy = new FunnelPolicy;

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->export($user));
    }

    public function test_user_without_permissions_cannot_view_funnels(): void
    {
        $this->seed(AdminPanelPermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['status' => 'active']);

        $policy = new FunnelPolicy;

        $this->assertFalse($policy->viewAny($user));
    }
}
