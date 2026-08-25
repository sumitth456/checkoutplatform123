<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\FulfillmentPolicy;
use Database\Seeders\AdminPanelPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FulfillmentPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_manage_and_export_delay_fulfillment(): void
    {
        $this->seed(AdminPanelPermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Admin');

        $policy = new FulfillmentPolicy;

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->export($user));
    }

    public function test_user_without_permissions_cannot_access_delay_fulfillment(): void
    {
        $this->seed(AdminPanelPermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['status' => 'active']);

        $policy = new FulfillmentPolicy;

        $this->assertFalse($policy->viewAny($user));
    }
}
