<?php

namespace App\Modules\Inventory\Services;

use App\Models\InventoryMovement;
use App\Models\InventoryReservation;
use App\Models\Variant;
use App\Modules\Inventory\Contracts\InventoryRepositoryInterface;
use App\Modules\Inventory\Data\InventoryLevel;
use App\Modules\Inventory\Enums\InventoryMovementType;
use App\Modules\Inventory\Enums\ReservationStatus;
use App\Modules\Variant\Enums\InventoryPolicy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(
        private readonly InventoryRepositoryInterface $inventory,
    ) {}

    public function getLevel(Variant $variant): InventoryLevel
    {
        $reserved = $this->inventory->getReservedQuantity($variant);

        return InventoryLevel::fromVariant($variant, $reserved);
    }

    public function adjust(
        Variant $variant,
        int $quantityChange,
        ?string $reason = null,
        ?string $notes = null,
        ?array $metadata = null,
    ): InventoryMovement {
        return $this->applyQuantityChange(
            variant: $variant,
            quantityChange: $quantityChange,
            type: InventoryMovementType::Adjustment,
            reason: $reason,
            notes: $notes,
            metadata: $metadata,
        );
    }

    public function restock(
        Variant $variant,
        int $quantity,
        ?string $reason = null,
        ?array $metadata = null,
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Restock quantity must be greater than zero.'],
            ]);
        }

        return $this->applyQuantityChange(
            variant: $variant,
            quantityChange: $quantity,
            type: InventoryMovementType::Restock,
            reason: $reason ?? 'restock',
            metadata: $metadata,
        );
    }

    public function reserve(
        Variant $variant,
        int $quantity,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?\DateTimeInterface $expiresAt = null,
        ?array $metadata = null,
    ): InventoryReservation {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Reservation quantity must be greater than zero.'],
            ]);
        }

        return DB::transaction(function () use ($variant, $quantity, $referenceType, $referenceId, $expiresAt, $metadata): InventoryReservation {
            $lockedVariant = $this->inventory->lockVariantForUpdate($variant);
            $this->assertTrackInventory($lockedVariant);
            $this->assertAvailableQuantity($lockedVariant, $quantity);

            return $this->inventory->createReservation([
                'variant_id' => $lockedVariant->id,
                'quantity' => $quantity,
                'status' => ReservationStatus::Active,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'expires_at' => $expiresAt,
                'metadata' => $metadata,
            ]);
        });
    }

    public function releaseReservation(string $reservationUuid): InventoryReservation
    {
        $reservation = $this->findActiveReservation($reservationUuid);

        return $this->inventory->updateReservation($reservation, [
            'status' => ReservationStatus::Released,
        ]);
    }

    public function commitReservation(string $reservationUuid): InventoryMovement
    {
        return DB::transaction(function () use ($reservationUuid): InventoryMovement {
            $reservation = $this->findActiveReservation($reservationUuid);
            $variant = $this->inventory->lockVariantForUpdate($reservation->variant);

            $movement = $this->applyQuantityChange(
                variant: $variant,
                quantityChange: -$reservation->quantity,
                type: InventoryMovementType::ReservationCommit,
                reason: 'reservation_commit',
                metadata: ['reservation_uuid' => $reservation->uuid],
                reservationId: $reservation->id,
            );

            $this->inventory->updateReservation($reservation, [
                'status' => ReservationStatus::Committed,
            ]);

            return $movement;
        });
    }

    public function listMovements(Variant $variant, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->inventory->paginateMovements($variant, $filters, $perPage);
    }

    public function listReservations(Variant $variant, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->inventory->paginateReservations($variant, $filters, $perPage);
    }

    private function applyQuantityChange(
        Variant $variant,
        int $quantityChange,
        InventoryMovementType $type,
        ?string $reason = null,
        ?string $notes = null,
        ?array $metadata = null,
        ?int $reservationId = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
    ): InventoryMovement {
        return DB::transaction(function () use (
            $variant,
            $quantityChange,
            $type,
            $reason,
            $notes,
            $metadata,
            $reservationId,
            $referenceType,
            $referenceId,
        ): InventoryMovement {
            $lockedVariant = $this->inventory->lockVariantForUpdate($variant);
            $this->assertTrackInventory($lockedVariant);

            $quantityBefore = $lockedVariant->inventory_quantity;
            $quantityAfter = $quantityBefore + $quantityChange;

            if ($quantityAfter < 0 && $lockedVariant->inventory_policy === InventoryPolicy::Deny) {
                throw ValidationException::withMessages([
                    'quantity' => ['Insufficient inventory for this operation.'],
                ]);
            }

            $lockedVariant->update(['inventory_quantity' => max(0, $quantityAfter)]);

            return $this->inventory->createMovement([
                'variant_id' => $lockedVariant->id,
                'inventory_reservation_id' => $reservationId,
                'type' => $type,
                'quantity_change' => $quantityChange,
                'quantity_before' => $quantityBefore,
                'quantity_after' => max(0, $quantityAfter),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reason' => $reason,
                'notes' => $notes,
                'metadata' => $metadata,
                'performed_by' => Auth::id(),
            ]);
        });
    }

    private function assertTrackInventory(Variant $variant): void
    {
        if (! $variant->track_inventory) {
            throw ValidationException::withMessages([
                'inventory' => ['Inventory tracking is disabled for this variant.'],
            ]);
        }
    }

    private function assertAvailableQuantity(Variant $variant, int $quantity): void
    {
        $level = $this->getLevel($variant);

        if ($level->available < $quantity && $variant->inventory_policy === InventoryPolicy::Deny) {
            throw ValidationException::withMessages([
                'quantity' => ['Insufficient available inventory to reserve.'],
            ]);
        }
    }

    private function findActiveReservation(string $uuid): InventoryReservation
    {
        $reservation = $this->inventory->findReservationByUuid($uuid);

        if ($reservation === null) {
            throw (new ModelNotFoundException)->setModel(InventoryReservation::class);
        }

        if ($reservation->status !== ReservationStatus::Active) {
            throw ValidationException::withMessages([
                'reservation' => ['Reservation is not active.'],
            ]);
        }

        return $reservation;
    }
}
