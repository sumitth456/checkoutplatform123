<?php



namespace Tests\Unit\Policies;



use App\Models\User;

use App\Support\Admin\AdminAuthorization;

use Database\Seeders\AdminPanelPermissionSeeder;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Spatie\Permission\PermissionRegistrar;

use Tests\TestCase;



class WebhooksAccessTest extends TestCase

{

    use RefreshDatabase;



    public function test_admin_can_view_and_manage_webhooks(): void

    {

        $this->seed(AdminPanelPermissionSeeder::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();



        $user = User::factory()->create(['status' => 'active']);

        $user->assignRole('Admin');



        $this->assertTrue(AdminAuthorization::can($user, 'webhooks.view'));

        $this->assertTrue(AdminAuthorization::can($user, 'webhooks.manage'));

    }



    public function test_user_without_permissions_cannot_access_webhooks(): void

    {

        $this->seed(AdminPanelPermissionSeeder::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();



        $user = User::factory()->create(['status' => 'active']);



        $this->assertFalse(AdminAuthorization::can($user, 'webhooks.view'));

        $this->assertFalse(AdminAuthorization::can($user, 'webhooks.manage'));

    }

}

