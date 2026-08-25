<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\RebillQueuePolicy;
use Database\Seeders\AdminPanelPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RebillQueuePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_manage_rebill_queue(): void
    {
        $this->seed(AdminPanelPermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Admin');

        $policy = new RebillQueuePolicy;
        $subscription = \App\Models\Subscription::factory()->make();

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->manage($user, $subscription));
    }
}
