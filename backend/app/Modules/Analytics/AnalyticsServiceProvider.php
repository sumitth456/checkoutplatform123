<?php

namespace App\Modules\Analytics;

use App\Modules\Analytics\Contracts\AnalyticsRepositoryInterface;
use App\Modules\Analytics\Repositories\AnalyticsRepository;
use App\Modules\Analytics\Services\AnalyticsService;
use Illuminate\Support\ServiceProvider;

class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AnalyticsRepositoryInterface::class, AnalyticsRepository::class);
        $this->app->singleton(AnalyticsService::class);
    }
}
