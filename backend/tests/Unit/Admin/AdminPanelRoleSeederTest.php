<?php



namespace Tests\Unit\Admin;



use App\Models\Role;

use Database\Seeders\AdminPanelPermissionSeeder;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Spatie\Permission\PermissionRegistrar;

use Tests\TestCase;



class AdminPanelRoleSeederTest extends TestCase

{

    use RefreshDatabase;



    public function test_seeder_creates_department_roles_with_permissions(): void

    {

        $this->seed(AdminPanelPermissionSeeder::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();



        $support = Role::query()->where('name', 'Support')->firstOrFail();

        $developer = Role::query()->where('name', 'Developer')->firstOrFail();



        $this->assertTrue($support->hasPermissionTo('customers.view'));

        $this->assertTrue($support->hasPermissionTo('orders.refund'));

        $this->assertFalse($support->hasPermissionTo('settings.update'));



        $this->assertTrue($developer->hasPermissionTo('webhooks.manage'));

        $this->assertTrue($developer->hasPermissionTo('settings.view'));

    }

}

