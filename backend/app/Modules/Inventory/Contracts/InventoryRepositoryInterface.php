<?php

namespace App\Modules\Inventory\Contracts;

use App\Models\InventoryMovement;
use App\Models\InventoryReservation;
use App\Models\Variant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InventoryRepositoryInterface
{
    public function getReservedQuantity(Variant $variant): int;

    public function createMovement(array $attributes): InventoryMovement;

    public function createReservation(array $attributes): InventoryReservation;

    public function findReservationByUuid(string $uuid): ?InventoryReservation;

    public function updateReservation(InventoryReservation $reservation, array $attributes): InventoryReservation;

    public function paginateMovements(Variant $variant, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function paginateReservations(Variant $variant, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function lockVariantForUpdate(Variant $variant): Variant;
}
