<?php

namespace Database\Factories;

use App\Models\Product;
use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Product\Enums\ProductType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => fake()->paragraph(),
            'short_description' => fake()->sentence(),
            'type' => ProductType::Digital,
            'status' => ProductStatus::Draft,
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####??')),
            'currency' => 'USD',
            'base_price' => fake()->randomFloat(2, 9, 299),
            'compare_at_price' => null,
            'is_taxable' => true,
            'requires_shipping' => false,
            'is_subscription' => false,
            'images' => [],
            'metadata' => [],
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => ProductStatus::Active,
            'published_at' => now(),
        ]);
    }

    public function subscription(): static
    {
        return $this->state(fn () => [
            'type' => ProductType::Subscription,
            'is_subscription' => true,
            'billing_interval' => 'month',
            'billing_interval_count' => 1,
            'trial_days' => 7,
        ]);
    }
}
