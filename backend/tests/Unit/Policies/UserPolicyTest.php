<?php



namespace Tests\Unit\Policies;



use App\Models\User;

use App\Policies\UserPolicy;

use Database\Seeders\AdminPanelPermissionSeeder;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Spatie\Permission\PermissionRegistrar;

use Tests\TestCase;



class UserPolicyTest extends TestCase

{

    use RefreshDatabase;



    public function test_admin_can_manage_other_users_but_not_delete_self(): void

    {

        $this->seed(AdminPanelPermissionSeeder::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();



        $admin = User::factory()->create(['status' => 'active']);

        $admin->assignRole('Admin');

        $other = User::factory()->create(['status' => 'active']);



        $policy = new UserPolicy;



        $this->assertTrue($policy->update($admin, $other));

        $this->assertTrue($policy->delete($admin, $other));

        $this->assertFalse($policy->delete($admin, $admin));

    }

}

