<?php

namespace App\Modules\Order\Jobs;

use App\Models\Order;
use App\Modules\Order\Contracts\FulfillmentServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** @deprecated Use CreateDelayedFulfillmentJob */
class ProcessOrderFulfillmentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}

    public function handle(FulfillmentServiceInterface $fulfillment): void
    {
        $fulfillment->scheduleAfterPayment($this->order->fresh());
    }
}
