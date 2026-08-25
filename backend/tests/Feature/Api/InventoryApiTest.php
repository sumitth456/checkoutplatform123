<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Variant;
use App\Modules\Inventory\Enums\ReservationStatus;
use App\Modules\Variant\Enums\InventoryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    private function trackedVariant(int $quantity = 100): array
    {
        $product = Product::factory()->create();
        $variant = Variant::factory()->tracked($quantity)->create([
            'product_id' => $product->id,
            'inventory_policy' => InventoryPolicy::Deny,
        ]);

        return [$product, $variant];
    }

    public function test_can_show_inventory_level(): void
    {
        [$product, $variant] = $this->trackedVariant(50);

        $response = $this->getJson("/api/v1/products/{$product->uuid}/variants/{$variant->uuid}/inventory");

        $response->assertOk()
            ->assertJsonPath('data.on_hand', 50)
            ->assertJsonPath('data.reserved', 0)
            ->assertJsonPath('data.available', 50)
            ->assertJsonPath('data.track_inventory', true);
    }

    public function test_can_adjust_inventory(): void
    {
        [$product, $variant] = $this->trackedVariant(10);

        $response = $this->postJson("/api/v1/products/{$product->uuid}/variants/{$variant->uuid}/inventory/adjust", [
            'quantity_change' => -3,
            'reason' => 'cycle_count',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.quantity_change', -3)
            ->assertJsonPath('data.quantity_after', 7);

        $this->assertSame(7, $variant->fresh()->inventory_quantity);
    }

    public function test_can_restock_inventory(): void
    {
        [$product, $variant] = $this->trackedVariant(5);

        $response = $this->postJson("/api/v1/products/{$product->uuid}/variants/{$variant->uuid}/inventory/restock", [
            'quantity' => 20,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'restock')
            ->assertJsonPath('data.quantity_after', 25);
    }

    public function test_adjustment_rejected_when_insufficient_stock(): void
    {
        [$product, $variant] = $this->trackedVariant(2);

        $response = $this->postJson("/api/v1/products/{$product->uuid}/variants/{$variant->uuid}/inventory/adjust", [
            'quantity_change' => -5,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_can_reserve_and_release_inventory(): void
    {
        [$product, $variant] = $this->trackedVariant(20);

        $reserveResponse = $this->postJson("/api/v1/products/{$product->uuid}/variants/{$variant->uuid}/inventory/reservations", [
            'quantity' => 5,
            'reference_type' => 'order',
            'reference_id' => 'ORD-1001',
        ]);

        $reserveResponse->assertCreated()
            ->assertJsonPath('data.quantity', 5)
            ->assertJsonPath('data.status', 'active');

        $reservationUuid = $reserveResponse->json('data.uuid');

        $this->getJson("/api/v1/products/{$product->uuid}/variants/{$variant->uuid}/inventory")
            ->assertJsonPath('data.reserved', 5)
            ->assertJsonPath('data.available', 15);

        $this->postJson("/api/v1/products/{$product->uuid}/variants/{$variant->uuid}/inventory/reservations/{$reservationUuid}/release")
            ->assertOk()
            ->assertJsonPath('data.status', 'released');

        $this->getJson("/api/v1/products/{$product->uuid}/variants/{$variant->uuid}/inventory")
            ->assertJsonPath('data.reserved', 0)
            ->assertJsonPath('data.available', 20);
    }

    public function test_can_commit_reservation_and_deduct_stock(): void
    {
        [$product, $variant] = $this->trackedVariant(20);

        $reservationUuid = $this->postJson("/api/v1/products/{$product->uuid}/variants/{$variant->uuid}/inventory/reservations", [
            'quantity' => 8,
        ])->json('data.uuid');

        $this->postJson("/api/v1/products/{$product->uuid}/variants/{$variant->uuid}/inventory/reservations/{$reservationUuid}/commit")
            ->assertCreated()
            ->assertJsonPath('data.type', 'reservation_commit')
            ->assertJsonPath('data.quantity_after', 12);

        $this->assertSame(12, $variant->fresh()->inventory_quantity);
    }

    public function test_can_list_inventory_movements(): void
    {
        [$product, $variant] = $this->trackedVariant(10);

        $this->postJson("/api/v1/products/{$product->uuid}/variants/{$variant->uuid}/inventory/restock", [
            'quantity' => 5,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->uuid}/variants/{$variant->uuid}/inventory/movements");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'restock');
    }

    public function test_inventory_operations_rejected_when_tracking_disabled(): void
    {
        $product = Product::factory()->create();
        $variant = Variant::factory()->create([
            'product_id' => $product->id,
            'track_inventory' => false,
        ]);

        $this->postJson("/api/v1/products/{$product->uuid}/variants/{$variant->uuid}/inventory/adjust", [
            'quantity_change' => 1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['inventory']);
    }

    public function test_can_list_reservations_by_status(): void
    {
        [$product, $variant] = $this->trackedVariant(30);

        $this->postJson("/api/v1/products/{$product->uuid}/variants/{$variant->uuid}/inventory/reservations", [
            'quantity' => 4,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->uuid}/variants/{$variant->uuid}/inventory/reservations?status=active");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', ReservationStatus::Active->value);
    }
}
