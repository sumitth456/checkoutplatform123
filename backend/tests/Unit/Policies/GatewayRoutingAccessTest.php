<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Support\Admin\AdminAuthorization;
use Database\Seeders\AdminPanelPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GatewayRoutingAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_manage_gateway_routing(): void
    {
        $this->seed(AdminPanelPermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Admin');

        $this->assertTrue(AdminAuthorization::can($user, 'gateway_routing.view'));
        $this->assertTrue(AdminAuthorization::can($user, 'gateway_routing.manage'));
    }

    public function test_user_without_permissions_cannot_view_gateway_routing(): void
    {
        $this->seed(AdminPanelPermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['status' => 'active']);

        $this->assertFalse(AdminAuthorization::can($user, 'gateway_routing.view'));
        $this->assertFalse(AdminAuthorization::can($user, 'gateway_routing.manage'));
    }
}
