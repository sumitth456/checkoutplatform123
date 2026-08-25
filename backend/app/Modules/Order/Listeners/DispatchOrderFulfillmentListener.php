<?php

namespace App\Modules\Order\Listeners;

use App\Modules\Order\Events\OrderPaid;
use App\Modules\Order\Jobs\CreateDelayedFulfillmentJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatchOrderFulfillmentListener implements ShouldQueue
{
    public string $queue;

    public function __construct()
    {
        $this->queue = (string) config('checkout.queue', 'default');
    }

    public function handle(OrderPaid $event): void
    {
        CreateDelayedFulfillmentJob::dispatch($event->order)->onQueue(
            (string) config('checkout.fulfillment_queue', 'default'),
        );
    }
}
