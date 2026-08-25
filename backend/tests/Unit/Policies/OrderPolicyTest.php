<?php

namespace Tests\Unit\Policies;

use App\Models\Order;
use App\Models\User;
use App\Policies\OrderPolicy;
use Database\Seeders\AdminPanelPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OrderPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_orders(): void
    {
        $this->seed(AdminPanelPermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Admin');

        $policy = new OrderPolicy;

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, Order::factory()->make()));
    }
}
