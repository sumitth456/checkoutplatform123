<?php

namespace App\Modules\Payment;

use App\Modules\Payment\Contracts\GatewayRoutingStrategyInterface;
use App\Modules\Payment\Contracts\IdempotencyStoreInterface;
use App\Modules\Payment\Contracts\PaymentLedgerInterface;
use App\Modules\Payment\Factory\PaymentGatewayFactory;
use App\Modules\Payment\Repositories\IdempotencyRepository;
use App\Modules\Payment\Repositories\PaymentLedgerRepository;
use App\Modules\Payment\Resilience\CircuitBreaker;
use App\Modules\Payment\Routing\ConfigGatewayRoutingStrategy;
use App\Modules\Payment\Services\PaymentOrchestrator;
use App\Modules\Payment\Services\WebhookDispatcher;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('payment.php'), 'payment');

        $this->app->singleton(CircuitBreaker::class);
        $this->app->singleton(PaymentGatewayFactory::class);

        $this->app->bind(GatewayRoutingStrategyInterface::class, ConfigGatewayRoutingStrategy::class);
        $this->app->bind(IdempotencyStoreInterface::class, IdempotencyRepository::class);
        $this->app->bind(PaymentLedgerInterface::class, PaymentLedgerRepository::class);

        $this->app->singleton(PaymentOrchestrator::class);
        $this->app->singleton(WebhookDispatcher::class);
    }

    public function boot(): void
    {
        //
    }
}
