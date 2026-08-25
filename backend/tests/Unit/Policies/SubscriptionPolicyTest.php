<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\SubscriptionPolicy;
use Database\Seeders\AdminPanelPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SubscriptionPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_subscriptions(): void
    {
        $this->seed(AdminPanelPermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Admin');

        $policy = new SubscriptionPolicy;
        $subscription = \App\Models\Subscription::factory()->make();

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->manageBilling($user, $subscription));
    }
}
