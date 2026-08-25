<?php

namespace App\Modules\Admin;

use App\Modules\Admin\Services\GatewayRoutingAdminService;
use App\Modules\Admin\Services\CouponAdminService;
use App\Modules\Admin\Services\FunnelAdminService;
use App\Modules\Admin\Services\WarehouseAdminService;
use App\Modules\Admin\Services\AdminAnalyticsService;
use App\Modules\Admin\Services\AdminDelayFulfillmentService;
use App\Modules\Admin\Services\AdminOrderRefundService;
use App\Modules\Admin\Services\AdminSubscriptionActionService;
use App\Modules\Admin\Services\PaymentGatewayLoadService;
use App\Modules\Admin\Services\PaymentGatewayAdminService;
use App\Modules\Admin\Services\AdminUserService;
use App\Modules\Admin\Services\PlatformConfigResolver;
use App\Modules\Admin\Services\AdminWebhookService;
use App\Modules\Admin\Services\RebillQueueService;
use App\Modules\Admin\Services\AdminLogService;
use App\Modules\Admin\Services\TransactionLedgerService;
use App\Modules\Admin\Services\DashboardMetricsService;
use App\Modules\Admin\Services\DashboardFilterApplicator;
use App\Modules\Admin\Services\DashboardFilterUrlBuilder;
use App\Modules\Admin\Services\GlobalDashboardFilterService;
use App\Modules\Admin\Services\OperationalDashboardService;
use App\Modules\Admin\Services\OrderDuplicateDetectionService;
use App\Modules\Admin\Services\OrderTimelineService;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DashboardMetricsService::class);
        $this->app->singleton(GlobalDashboardFilterService::class);
        $this->app->singleton(DashboardFilterUrlBuilder::class);
        $this->app->singleton(OperationalDashboardService::class);
        $this->app->singleton(DashboardFilterApplicator::class);
        $this->app->singleton(OrderDuplicateDetectionService::class);
        $this->app->singleton(OrderTimelineService::class);
        $this->app->singleton(AdminOrderRefundService::class);
        $this->app->singleton(AdminUserService::class);
        $this->app->singleton(PlatformConfigResolver::class);
        $this->app->singleton(PaymentGatewayLoadService::class);
        $this->app->singleton(PaymentGatewayAdminService::class);
        $this->app->singleton(GatewayRoutingAdminService::class);
        $this->app->singleton(TransactionLedgerService::class);
        $this->app->singleton(AdminDelayFulfillmentService::class);
        $this->app->singleton(WarehouseAdminService::class);
        $this->app->singleton(CouponAdminService::class);
        $this->app->singleton(FunnelAdminService::class);
        $this->app->singleton(AdminAnalyticsService::class);
        $this->app->singleton(AdminLogService::class);
        $this->app->singleton(AdminWebhookService::class);
        $this->app->singleton(RebillQueueService::class);
        $this->app->singleton(AdminSubscriptionActionService::class);
    }

    public function boot(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                app(AdminUserService::class)->recordLogin($event->user, request()->ip());
            }
        });
    }
}
