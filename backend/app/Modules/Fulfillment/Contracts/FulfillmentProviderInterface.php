<?php

namespace App\Modules\Fulfillment\Contracts;

use App\Models\Fulfillment;
use App\Modules\Order\DTOs\WarehouseShipmentResponseDTO;

interface FulfillmentProviderInterface
{
    public function submit(Fulfillment $fulfillment): WarehouseShipmentResponseDTO;

    public function testConnection(): bool;
}
