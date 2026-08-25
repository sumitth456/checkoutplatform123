<?php

namespace App\Modules\Order;

use App\Modules\Order\Actions\CalculateOrderTotalsAction;
use App\Modules\Order\Actions\CaptureRequestContextAction;
use App\Modules\Order\Actions\CompletePaidCheckoutAction;
use App\Modules\Order\Actions\CreateDelayedFulfillmentAction;
use App\Modules\Order\Actions\CreateFunnelOrderAction;
use App\Modules\Order\Actions\CreateStripePaymentIntentAction;
use App\Modules\Order\Actions\CreateRebillOrderAction;
use App\Modules\Order\Actions\GenerateOrderNumberAction;
use App\Modules\Order\Actions\GenerateRebillOrderNumberAction;
use App\Modules\Order\Actions\LogCheckoutPaymentFailureAction;
use App\Modules\Order\Actions\PrepareCheckoutAction;
use App\Modules\Order\Actions\ResolveCampaignCheckoutAction;
use App\Modules\Order\Actions\ResolveFunnelAction;
use App\Modules\Order\Actions\ResolveLineItemAction;
use App\Modules\Order\Actions\ResolveOrCreateCustomerAction;
use App\Modules\Order\Actions\SubmitFulfillmentToWarehouseAction;
use App\Modules\Order\Actions\VerifyInventoryAction;
use App\Modules\Order\Console\ProcessDueFulfillments;
use App\Modules\Order\Contracts\CatalogRepositoryInterface;
use App\Modules\Order\Contracts\CheckoutPaymentServiceInterface;
use App\Modules\Order\Contracts\CustomerRepositoryInterface;
use App\Modules\Order\Contracts\FulfillmentRepositoryInterface;
use App\Modules\Order\Contracts\FulfillmentServiceInterface;
use App\Modules\Order\Contracts\OrderRepositoryInterface;
use App\Modules\Order\Contracts\WarehouseApiClientInterface;
use App\Modules\Order\Events\CheckoutPaymentFailed;
use App\Modules\Order\Events\OrderPaid;
use App\Modules\Order\Listeners\CreateRebillOrderListener;
use App\Modules\Order\Listeners\CreateSubscriptionsFromOrderListener;
use App\Modules\Order\Listeners\DispatchOrderFulfillmentListener;
use App\Modules\Order\Listeners\LogCheckoutPaymentFailureListener;
use App\Modules\Order\Listeners\ReconcileStripePaymentWithOrderListener;
use App\Modules\Order\Repositories\CatalogRepository;
use App\Modules\Order\Repositories\CustomerRepository;
use App\Modules\Order\Repositories\FulfillmentRepository;
use App\Modules\Order\Repositories\OrderRepository;
use App\Modules\Order\Services\CheckoutPaymentService;
use App\Modules\Order\Services\CheckoutSubscriptionService;
use App\Modules\Order\Services\CouponApplicationService;
use App\Modules\Order\Services\CouponValidationService;
use App\Modules\Order\Services\DelayFulfillmentEngineService;
use App\Modules\Order\Services\FunnelOrderService;
use App\Modules\Order\Services\FulfillmentService;
use App\Modules\Order\Services\OrderService;
use App\Modules\Order\Services\PaymentFailureLoggerService;
use App\Modules\Order\Services\StripeCheckoutService;
use App\Modules\Order\Services\StripePaymentIntentService;
use App\Modules\Order\Services\WarehouseApiClient;
use App\Modules\Subscription\Events\SubscriptionRebillFailed;
use App\Modules\Subscription\Events\SubscriptionRebilled;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class OrderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('checkout.php'), 'checkout');
        $this->mergeConfigFrom(config_path('fulfillment.php'), 'fulfillment');

        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
        $this->app->bind(CustomerRepositoryInterface::class, CustomerRepository::class);
        $this->app->bind(CatalogRepositoryInterface::class, CatalogRepository::class);
        $this->app->bind(FulfillmentRepositoryInterface::class, FulfillmentRepository::class);
        $this->app->bind(WarehouseApiClientInterface::class, WarehouseApiClient::class);
        $this->app->bind(CheckoutPaymentServiceInterface::class, CheckoutPaymentService::class);
        $this->app->bind(FulfillmentServiceInterface::class, FulfillmentService::class);

        $this->app->singleton(OrderService::class);
        $this->app->singleton(FunnelOrderService::class);
        $this->app->singleton(StripeCheckoutService::class);
        $this->app->singleton(CheckoutPaymentService::class);
        $this->app->singleton(StripePaymentIntentService::class);
        $this->app->singleton(PaymentFailureLoggerService::class);
        $this->app->singleton(CheckoutSubscriptionService::class);
        $this->app->singleton(CouponApplicationService::class);
        $this->app->singleton(CouponValidationService::class);
        $this->app->singleton(ResolveCampaignCheckoutAction::class);
        $this->app->singleton(DelayFulfillmentEngineService::class);
        $this->app->singleton(FulfillmentService::class);
        $this->app->singleton(WarehouseApiClient::class);
        $this->app->singleton(WarehouseConfigResolver::class);

        $this->app->singleton(CaptureRequestContextAction::class);
        $this->app->singleton(ResolveFunnelAction::class);
        $this->app->singleton(ResolveLineItemAction::class);
        $this->app->singleton(VerifyInventoryAction::class);
        $this->app->singleton(CalculateOrderTotalsAction::class);
        $this->app->singleton(ResolveOrCreateCustomerAction::class);
        $this->app->singleton(GenerateOrderNumberAction::class);
        $this->app->singleton(GenerateRebillOrderNumberAction::class);
        $this->app->singleton(CreateRebillOrderAction::class);
        $this->app->singleton(CreateFunnelOrderAction::class);
        $this->app->singleton(PrepareCheckoutAction::class);
        $this->app->singleton(CompletePaidCheckoutAction::class);
        $this->app->singleton(LogCheckoutPaymentFailureAction::class);
        $this->app->singleton(CreateStripePaymentIntentAction::class);
        $this->app->singleton(CreateDelayedFulfillmentAction::class);
        $this->app->singleton(SubmitFulfillmentToWarehouseAction::class);
    }

    public function boot(): void
    {
        $this->commands([
            ProcessDueFulfillments::class,
        ]);

        Event::listen(OrderPaid::class, CreateSubscriptionsFromOrderListener::class);
        Event::listen(OrderPaid::class, DispatchOrderFulfillmentListener::class);
        Event::listen(CheckoutPaymentFailed::class, LogCheckoutPaymentFailureListener::class);
        Event::listen(PaymentEventReceived::class, ReconcileStripePaymentWithOrderListener::class);
        Event::listen(SubscriptionRebilled::class, [CreateRebillOrderListener::class, 'handleRebilled']);
        Event::listen(SubscriptionRebillFailed::class, [CreateRebillOrderListener::class, 'handleRebillFailed']);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $time = (string) config('fulfillment.scheduler.time', '06:00');
            $timezone = (string) config('fulfillment.scheduler.timezone', config('app.timezone'));

            $schedule->command('fulfillments:process-due')
                ->dailyAt($time)
                ->timezone($timezone);
        });
    }
}
