<?php



namespace Tests\Unit\Policies;



use App\Models\User;

use App\Policies\AdminActivityLogPolicy;

use App\Policies\CheckoutPaymentFailurePolicy;

use App\Policies\StripeWebhookEventPolicy;

use Database\Seeders\AdminPanelPermissionSeeder;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Spatie\Permission\PermissionRegistrar;

use Tests\TestCase;



class AdminLogPolicyTest extends TestCase

{

    use RefreshDatabase;



    public function test_admin_role_can_view_and_export_logs(): void

    {

        $this->seed(AdminPanelPermissionSeeder::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();



        $user = User::factory()->create(['status' => 'active']);

        $user->assignRole('Admin');



        $activityPolicy = new AdminActivityLogPolicy;

        $failurePolicy = new CheckoutPaymentFailurePolicy;

        $webhookPolicy = new StripeWebhookEventPolicy;



        $this->assertTrue($activityPolicy->viewAny($user));

        $this->assertTrue($activityPolicy->export($user));

        $this->assertTrue($failurePolicy->viewAny($user));

        $this->assertTrue($webhookPolicy->export($user));

        $this->assertFalse($activityPolicy->create($user));

    }



    public function test_user_without_permissions_cannot_access_logs(): void

    {

        $this->seed(AdminPanelPermissionSeeder::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();



        $user = User::factory()->create(['status' => 'active']);



        $policy = new AdminActivityLogPolicy;



        $this->assertFalse($policy->viewAny($user));

        $this->assertFalse($policy->export($user));

    }

}

