<?php

namespace App\Modules\Order\Events;

use App\Models\Fulfillment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FulfillmentRecordCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Fulfillment $fulfillment,
    ) {}
}
