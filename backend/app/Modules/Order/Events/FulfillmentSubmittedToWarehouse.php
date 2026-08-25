<?php

namespace App\Modules\Order\Events;

use App\Models\Fulfillment;
use App\Modules\Order\DTOs\WarehouseShipmentResponseDTO;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FulfillmentSubmittedToWarehouse
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Fulfillment $fulfillment,
        public readonly WarehouseShipmentResponseDTO $response,
    ) {}
}
