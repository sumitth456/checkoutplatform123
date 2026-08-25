<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Variant;
use App\Modules\Inventory\Http\Requests\AdjustInventoryRequest;
use App\Modules\Inventory\Http\Requests\ListInventoryMovementsRequest;
use App\Modules\Inventory\Http\Requests\ListInventoryReservationsRequest;
use App\Modules\Inventory\Http\Requests\ReserveInventoryRequest;
use App\Modules\Inventory\Http\Requests\RestockInventoryRequest;
use App\Modules\Inventory\Http\Resources\InventoryLevelResource;
use App\Modules\Inventory\Http\Resources\InventoryMovementResource;
use App\Modules\Inventory\Http\Resources\InventoryReservationResource;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventory,
    ) {}

    public function show(Product $product, Variant $variant): InventoryLevelResource
    {
        return new InventoryLevelResource($this->inventory->getLevel($variant));
    }

    public function adjust(AdjustInventoryRequest $request, Product $product, Variant $variant): JsonResponse
    {
        $movement = $this->inventory->adjust(
            variant: $variant,
            quantityChange: $request->integer('quantity_change'),
            reason: $request->input('reason'),
            notes: $request->input('notes'),
            metadata: $request->input('metadata'),
        );

        return (new InventoryMovementResource($movement))
            ->response()
            ->setStatusCode(201);
    }

    public function restock(RestockInventoryRequest $request, Product $product, Variant $variant): JsonResponse
    {
        $movement = $this->inventory->restock(
            variant: $variant,
            quantity: $request->integer('quantity'),
            reason: $request->input('reason'),
            metadata: $request->input('metadata'),
        );

        return (new InventoryMovementResource($movement))
            ->response()
            ->setStatusCode(201);
    }

    public function movements(ListInventoryMovementsRequest $request, Product $product, Variant $variant): AnonymousResourceCollection
    {
        $paginator = $this->inventory->listMovements(
            variant: $variant,
            filters: $request->validated(),
            perPage: (int) $request->integer('per_page', 15),
        );

        return InventoryMovementResource::collection($paginator);
    }

    public function reserve(ReserveInventoryRequest $request, Product $product, Variant $variant): JsonResponse
    {
        $reservation = $this->inventory->reserve(
            variant: $variant,
            quantity: $request->integer('quantity'),
            referenceType: $request->input('reference_type'),
            referenceId: $request->input('reference_id'),
            expiresAt: $request->date('expires_at'),
            metadata: $request->input('metadata'),
        );

        return (new InventoryReservationResource($reservation))
            ->response()
            ->setStatusCode(201);
    }

    public function reservations(ListInventoryReservationsRequest $request, Product $product, Variant $variant): AnonymousResourceCollection
    {
        $paginator = $this->inventory->listReservations(
            variant: $variant,
            filters: $request->validated(),
            perPage: (int) $request->integer('per_page', 15),
        );

        return InventoryReservationResource::collection($paginator);
    }

    public function releaseReservation(Product $product, Variant $variant, string $reservation): InventoryReservationResource
    {
        $released = $this->inventory->releaseReservation($reservation);

        if ($released->variant_id !== $variant->id) {
            abort(404);
        }

        return new InventoryReservationResource($released);
    }

    public function commitReservation(Product $product, Variant $variant, string $reservation): JsonResponse
    {
        $movement = $this->inventory->commitReservation($reservation);

        if ($movement->variant_id !== $variant->id) {
            abort(404);
        }

        return (new InventoryMovementResource($movement))
            ->response()
            ->setStatusCode(201);
    }
}
