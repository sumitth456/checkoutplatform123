<?php

namespace App\Modules\LandingPage\Listeners;

use App\Modules\LandingPage\Services\LandingPageAnalyticsService;
use App\Modules\Order\Events\OrderPaid;

final class RecordLandingPageConversionListener
{
    public function __construct(
        private readonly LandingPageAnalyticsService $analytics,
    ) {}

    public function handle(OrderPaid $event): void
    {
        $page = $this->analytics->resolvePageFromOrder($event->order);

        if ($page === null) {
            return;
        }

        $this->analytics->recordConversion($page, (float) $event->order->grand_total);
    }
}
