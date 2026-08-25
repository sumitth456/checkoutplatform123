<?php

namespace App\Modules\Order\Contracts;

use App\Modules\Order\DTOs\WarehouseShipmentRequestDTO;
use App\Modules\Order\DTOs\WarehouseShipmentResponseDTO;

interface WarehouseApiClientInterface
{
    public function createShipment(WarehouseShipmentRequestDTO $request): WarehouseShipmentResponseDTO;
}
