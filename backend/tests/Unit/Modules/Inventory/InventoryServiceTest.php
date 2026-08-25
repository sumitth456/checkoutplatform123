<?php

namespace Tests\Unit\Modules\Inventory;

use App\Models\Product;
use App\Models\Variant;
use App\Modules\Inventory\Enums\ReservationStatus;
use App\Modules\Inventory\Repositories\InventoryRepository;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Variant\Enums\InventoryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new InventoryService(new InventoryRepository);
    }

    private function trackedVariant(int $quantity = 50): Variant
    {
        $product = Product::factory()->create();

        return Variant::factory()->tracked($quantity)->create([
            'product_id' => $product->id,
            'inventory_policy' => InventoryPolicy::Deny,
        ]);
    }

    public function test_get_level_calculates_available_quantity(): void
    {
        $variant = $this->trackedVariant(20);

        $this->service->reserve($variant, 6);

        $level = $this->service->getLevel($variant->fresh());

        $this->assertSame(20, $level->onHand);
        $this->assertSame(6, $level->reserved);
        $this->assertSame(14, $level->available);
    }

    public function test_commit_reservation_updates_stock_and_status(): void
    {
        $variant = $this->trackedVariant(15);
        $reservation = $this->service->reserve($variant, 5);

        $movement = $this->service->commitReservation($reservation->uuid);

        $this->assertSame(10, $variant->fresh()->inventory_quantity);
        $this->assertSame(ReservationStatus::Committed, $reservation->fresh()->status);
        $this->assertSame(-5, $movement->quantity_change);
    }

    public function test_reserve_fails_when_available_quantity_insufficient(): void
    {
        $variant = $this->trackedVariant(3);

        $this->expectException(ValidationException::class);

        $this->service->reserve($variant, 10);
    }

    public function test_adjustment_creates_audit_movement(): void
    {
        $variant = $this->trackedVariant(8);

        $movement = $this->service->adjust($variant, -2, 'damaged');

        $this->assertSame(6, $variant->fresh()->inventory_quantity);
        $this->assertSame(-2, $movement->quantity_change);
        $this->assertSame('damaged', $movement->reason);
    }
}
