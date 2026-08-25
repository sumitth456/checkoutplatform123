<?php

namespace App\Modules\Subscription;

use App\Modules\Payment\Events\PaymentEventReceived;
use App\Modules\Subscription\Console\ProcessDueSubscriptions;
use App\Modules\Subscription\Console\ProcessDunningSubscriptions;
use App\Modules\Subscription\Contracts\BillingLedgerInterface;
use App\Modules\Subscription\Contracts\SubscriptionRepositoryInterface;
use App\Modules\Subscription\Events\SubscriptionRebillFailed;
use App\Modules\Subscription\Listeners\ReconcileSubscriptionPaymentWebhookListener;
use App\Modules\Subscription\Listeners\ScheduleDunningOnRebillFailedListener;
use App\Modules\Subscription\Repositories\BillingLedgerRepository;
use App\Modules\Subscription\Repositories\SubscriptionRepository;
use App\Modules\Subscription\Services\BillingCycleAdvancer;
use App\Modules\Subscription\Services\BillingScheduleCalculator;
use App\Modules\Subscription\Services\CycleAmountResolver;
use App\Modules\Subscription\Services\DunningService;
use App\Modules\Subscription\Services\FailedPaymentRetryService;
use App\Modules\Subscription\Services\RebillService;
use App\Modules\Subscription\Services\RetryPolicyResolver;
use App\Modules\Subscription\Services\SubscriptionEngineService;
use App\Modules\Subscription\Services\SubscriptionLifecycleService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class SubscriptionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BillingLedgerInterface::class, BillingLedgerRepository::class);
        $this->app->bind(SubscriptionRepositoryInterface::class, SubscriptionRepository::class);

        $this->app->singleton(BillingScheduleCalculator::class);
        $this->app->singleton(CycleAmountResolver::class);
        $this->app->singleton(BillingCycleAdvancer::class);
        $this->app->singleton(RetryPolicyResolver::class);
        $this->app->singleton(DunningService::class);
        $this->app->singleton(RebillService::class);
        $this->app->singleton(SubscriptionLifecycleService::class);
        $this->app->singleton(FailedPaymentRetryService::class);
        $this->app->singleton(SubscriptionEngineService::class);
    }

    public function boot(): void
    {
        $this->commands([
            ProcessDueSubscriptions::class,
            ProcessDunningSubscriptions::class,
        ]);

        Event::listen(SubscriptionRebillFailed::class, ScheduleDunningOnRebillFailedListener::class);
        Event::listen(PaymentEventReceived::class, ReconcileSubscriptionPaymentWebhookListener::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $billingFrequency = config('subscription.scheduler.frequency', 'hourly');
            $billingEvent = $schedule->command('subscriptions:process-due');

            match ($billingFrequency) {
                'every_fifteen_minutes' => $billingEvent->everyFifteenMinutes(),
                'every_thirty_minutes' => $billingEvent->everyThirtyMinutes(),
                default => $billingEvent->hourly(),
            };

            $dunningFrequency = config('subscription.scheduler.dunning_frequency', 'hourly');
            $dunningEvent = $schedule->command('subscriptions:process-dunning');

            match ($dunningFrequency) {
                'every_fifteen_minutes' => $dunningEvent->everyFifteenMinutes(),
                'every_thirty_minutes' => $dunningEvent->everyThirtyMinutes(),
                default => $dunningEvent->hourly(),
            };
        });
    }
}
