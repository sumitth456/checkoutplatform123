<?php



namespace Tests\Unit\Policies;



use App\Models\Role;

use App\Models\User;

use App\Policies\RolePolicy;

use Database\Seeders\AdminPanelPermissionSeeder;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Spatie\Permission\PermissionRegistrar;

use Tests\TestCase;



class RolePolicyTest extends TestCase

{

    use RefreshDatabase;



    public function test_admin_role_is_protected_from_update_and_delete(): void

    {

        $this->seed(AdminPanelPermissionSeeder::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();



        $user = User::factory()->create(['status' => 'active']);

        $user->assignRole('Admin');



        $adminRole = Role::query()->where('name', 'Admin')->firstOrFail();

        $customRole = Role::query()->create([

            'name' => 'QA',

            'guard_name' => 'web',

            'description' => 'QA role',

        ]);



        $policy = new RolePolicy;



        $this->assertFalse($policy->update($user, $adminRole));

        $this->assertFalse($policy->delete($user, $adminRole));

        $this->assertTrue($policy->update($user, $customRole));

        $this->assertTrue($policy->delete($user, $customRole));

    }

}

