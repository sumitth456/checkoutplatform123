<?php

namespace App\Modules\Order\Jobs;

use App\Models\Order;
use App\Modules\Order\Actions\CreateDelayedFulfillmentAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateDelayedFulfillmentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}

    public function handle(CreateDelayedFulfillmentAction $action): void
    {
        $action->execute($this->order);
    }
}
