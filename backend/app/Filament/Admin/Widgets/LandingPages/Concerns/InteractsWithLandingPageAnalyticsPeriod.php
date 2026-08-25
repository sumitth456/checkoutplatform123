<?php

namespace App\Filament\Admin\Widgets\LandingPages\Concerns;

use App\Modules\Analytics\Data\AnalyticsPeriod;
use App\Modules\LandingPage\Services\LandingPageAnalyticsService;

trait InteractsWithLandingPageAnalyticsPeriod
{
    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    protected function landingPageAnalyticsPeriod(): AnalyticsPeriod
    {
        return AnalyticsPeriod::fromRequest(
            $this->dateFrom ?? request()->query('from'),
            $this->dateTo ?? request()->query('to'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function landingPageAnalyticsSnapshot(): array
    {
        return app(LandingPageAnalyticsService::class)->snapshot($this->landingPageAnalyticsPeriod());
    }
}
