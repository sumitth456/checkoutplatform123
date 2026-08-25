<?php

namespace App\Modules\StripeWebhook;

use App\Modules\StripeWebhook\Contracts\StripeWebhookRepositoryInterface;
use App\Modules\StripeWebhook\Events\StripeWebhookProcessed;
use App\Modules\StripeWebhook\Events\StripeWebhookProcessingFailed;
use App\Modules\StripeWebhook\Events\StripeWebhookReceived;
use App\Modules\StripeWebhook\Listeners\DispatchPaymentEventReceivedListener;
use App\Modules\StripeWebhook\Listeners\LogStripeWebhookListener;
use App\Modules\StripeWebhook\Listeners\RecordStripePaymentLedgerListener;
use App\Modules\StripeWebhook\Repositories\StripeWebhookRepository;
use App\Modules\StripeWebhook\Services\StripeEventNormalizerService;
use App\Modules\StripeWebhook\Services\StripeSignatureVerifierService;
use App\Modules\StripeWebhook\Services\StripeWebhookIngressService;
use App\Modules\StripeWebhook\Services\StripeWebhookProcessorService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class StripeWebhookServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('stripe_webhook.php'), 'stripe_webhook');

        $this->app->bind(StripeWebhookRepositoryInterface::class, StripeWebhookRepository::class);

        $this->app->singleton(StripeSignatureVerifierService::class);
        $this->app->singleton(StripeEventNormalizerService::class);
        $this->app->singleton(StripeWebhookIngressService::class);
        $this->app->singleton(StripeWebhookProcessorService::class);
    }

    public function boot(): void
    {
        $logListener = LogStripeWebhookListener::class;

        Event::listen(StripeWebhookReceived::class, [$logListener, 'handleReceived']);
        Event::listen(StripeWebhookProcessed::class, [$logListener, 'handleProcessed']);
        Event::listen(StripeWebhookProcessingFailed::class, [$logListener, 'handleFailed']);

        Event::listen(StripeWebhookProcessed::class, RecordStripePaymentLedgerListener::class);
        Event::listen(StripeWebhookProcessed::class, DispatchPaymentEventReceivedListener::class);
    }
}
