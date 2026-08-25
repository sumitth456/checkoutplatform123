<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Variant;
use App\Modules\Variant\Enums\InventoryPolicy;
use App\Modules\Variant\Enums\VariantStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Variant>
 */
class VariantFactory extends Factory
{
    protected $model = Variant::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'product_id' => Product::factory(),
            'name' => fake()->words(2, true),
            'sku' => strtoupper(fake()->unique()->bothify('VAR-####??')),
            'barcode' => fake()->optional()->ean13(),
            'currency' => 'USD',
            'price' => fake()->randomFloat(2, 5, 499),
            'compare_at_price' => null,
            'cost' => fake()->randomFloat(2, 1, 200),
            'inventory_quantity' => fake()->numberBetween(0, 100),
            'inventory_policy' => InventoryPolicy::Deny,
            'track_inventory' => false,
            'weight' => fake()->randomFloat(3, 0.1, 10),
            'weight_unit' => 'kg',
            'options' => [],
            'images' => [],
            'is_default' => false,
            'status' => VariantStatus::Active,
            'metadata' => [],
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    public function tracked(int $quantity = 100): static
    {
        return $this->state(fn () => [
            'track_inventory' => true,
            'inventory_quantity' => $quantity,
        ]);
    }
}
