<?php

namespace App\Modules\Inventory\Repositories;

use App\Models\InventoryMovement;
use App\Models\InventoryReservation;
use App\Models\Variant;
use App\Modules\Inventory\Contracts\InventoryRepositoryInterface;
use App\Modules\Inventory\Enums\ReservationStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InventoryRepository implements InventoryRepositoryInterface
{
    public function getReservedQuantity(Variant $variant): int
    {
        return (int) $variant->inventoryReservations()
            ->where('status', ReservationStatus::Active)
            ->sum('quantity');
    }

    public function createMovement(array $attributes): InventoryMovement
    {
        return InventoryMovement::query()->create($attributes);
    }

    public function createReservation(array $attributes): InventoryReservation
    {
        return InventoryReservation::query()->create($attributes);
    }

    public function findReservationByUuid(string $uuid): ?InventoryReservation
    {
        return InventoryReservation::query()->where('uuid', $uuid)->first();
    }

    public function updateReservation(InventoryReservation $reservation, array $attributes): InventoryReservation
    {
        $reservation->update($attributes);

        return $reservation->fresh();
    }

    public function paginateMovements(Variant $variant, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $variant->inventoryMovements()->newQuery();

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $direction = $filters['direction'] ?? 'desc';

        return $query
            ->orderBy('created_at', $direction)
            ->paginate($perPage);
    }

    public function paginateReservations(Variant $variant, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $variant->inventoryReservations()->newQuery();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function lockVariantForUpdate(Variant $variant): Variant
    {
        return Variant::query()->whereKey($variant->id)->lockForUpdate()->firstOrFail();
    }
}
