<?php

namespace App\Modules\Email;

use App\Models\EmailTemplate;
use App\Modules\Email\Console\ProcessEmailQueueCommand;
use App\Modules\Email\Listeners\DispatchEmailAutomationListener;
use App\Modules\Email\Observers\EmailTemplateObserver;
use App\Modules\Email\Providers\HttpEmailProviderDriver;
use App\Modules\Email\Providers\SmtpEmailProviderDriver;
use App\Modules\Email\Services\EmailAnalyticsService;
use App\Modules\Email\Services\EmailAutomationService;
use App\Modules\Email\Services\EmailDispatchService;
use App\Modules\Email\Services\EmailProviderManager;
use App\Modules\Email\Services\EmailTemplateRenderer;
use App\Modules\Email\Services\EmailVariableResolver;
use App\Modules\Order\Events\CheckoutPaymentFailed;
use App\Modules\Order\Events\FulfillmentRecordCreated;
use App\Modules\Order\Events\FulfillmentSubmittedToWarehouse;
use App\Modules\Order\Events\OrderPaid;
use App\Modules\Order\Events\OrderPlaced;
use App\Modules\Subscription\Events\SubscriptionCancelled;
use App\Modules\Subscription\Events\SubscriptionCreated;
use App\Modules\Subscription\Events\SubscriptionPaused;
use App\Modules\Subscription\Events\SubscriptionRebillFailed;
use App\Modules\Subscription\Events\SubscriptionRebilled;
use App\Modules\Subscription\Events\SubscriptionResumed;
use App\Modules\Subscription\Events\SubscriptionRetryScheduled;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EmailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('email.php'), 'email');

        $this->app->singleton(SmtpEmailProviderDriver::class);
        $this->app->singleton(KlaviyoEmailProviderDriver::class);
        $this->app->singleton(HttpEmailProviderDriver::class);
        $this->app->singleton(EmailProviderManager::class);
        $this->app->singleton(EmailVariableResolver::class);
        $this->app->singleton(EmailTemplateRenderer::class);
        $this->app->singleton(EmailDispatchService::class);
        $this->app->singleton(EmailAutomationService::class);
        $this->app->singleton(EmailAnalyticsService::class);
    }

    public function boot(): void
    {
        EmailTemplate::observe(EmailTemplateObserver::class);

        $this->commands([
            ProcessEmailQueueCommand::class,
        ]);

        $listener = DispatchEmailAutomationListener::class;

        Event::listen(OrderPlaced::class, [$listener, 'handleOrderPlaced']);
        Event::listen(OrderPaid::class, [$listener, 'handleOrderPaid']);
        Event::listen(CheckoutPaymentFailed::class, [$listener, 'handlePaymentFailed']);
        Event::listen(FulfillmentRecordCreated::class, [$listener, 'handleFulfillmentCreated']);
        Event::listen(FulfillmentSubmittedToWarehouse::class, [$listener, 'handleFulfillmentSubmitted']);
        Event::listen(SubscriptionCreated::class, [$listener, 'handleSubscriptionCreated']);
        Event::listen(SubscriptionRebilled::class, [$listener, 'handleSubscriptionRebilled']);
        Event::listen(SubscriptionRebillFailed::class, [$listener, 'handleSubscriptionRebillFailed']);
        Event::listen(SubscriptionRetryScheduled::class, [$listener, 'handleSubscriptionRetryScheduled']);
        Event::listen(SubscriptionCancelled::class, [$listener, 'handleSubscriptionCancelled']);
        Event::listen(SubscriptionPaused::class, [$listener, 'handleSubscriptionPaused']);
        Event::listen(SubscriptionResumed::class, [$listener, 'handleSubscriptionResumed']);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('email:process-queue')->everyMinute();
        });
    }
}
