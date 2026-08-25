<?php



namespace Tests\Unit\Policies;



use App\Models\PlatformSetting;

use App\Models\User;

use App\Policies\PlatformSettingPolicy;

use Database\Seeders\AdminPanelPermissionSeeder;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Spatie\Permission\PermissionRegistrar;

use Tests\TestCase;



class PlatformSettingPolicyTest extends TestCase

{

    use RefreshDatabase;



    public function test_admin_can_view_and_update_settings(): void

    {

        $this->seed(AdminPanelPermissionSeeder::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();



        $user = User::factory()->create(['status' => 'active']);

        $user->assignRole('Admin');



        $policy = new PlatformSettingPolicy;

        $settings = PlatformSetting::current();



        $this->assertTrue($policy->viewAny($user));

        $this->assertTrue($policy->update($user, $settings));

        $this->assertFalse($policy->create($user));

    }



    public function test_user_without_permissions_cannot_manage_settings(): void

    {

        $this->seed(AdminPanelPermissionSeeder::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();



        $user = User::factory()->create(['status' => 'active']);

        $policy = new PlatformSettingPolicy;

        $settings = PlatformSetting::current();



        $this->assertFalse($policy->view($user, $settings));

        $this->assertFalse($policy->update($user, $settings));

    }

}

