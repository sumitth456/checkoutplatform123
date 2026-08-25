<?php

namespace App\Modules\Affiliate\Listeners;

use App\Modules\Affiliate\Services\AffiliateConversionService;
use App\Modules\Order\Events\OrderPaid;

class RecordAffiliateConversionListener
{
    public function __construct(
        private readonly AffiliateConversionService $conversions,
    ) {}

    public function handle(OrderPaid $event): void
    {
        $this->conversions->recordOrderConversion($event->order);
    }
}
