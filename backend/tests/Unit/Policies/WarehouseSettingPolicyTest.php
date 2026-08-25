<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Models\WarehouseSetting;
use App\Policies\WarehouseSettingPolicy;
use Database\Seeders\AdminPanelPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WarehouseSettingPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_warehouse_settings(): void
    {
        $this->seed(AdminPanelPermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Admin');

        $policy = new WarehouseSettingPolicy;

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, WarehouseSetting::current()));
    }
}
