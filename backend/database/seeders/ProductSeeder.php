<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::query()->count() === 0) {
            Product::factory()
                ->count(5)
                ->create();

            Product::factory()
                ->count(3)
                ->active()
                ->create();
        }

        Product::query()->firstOrCreate(
            ['slug' => 'pro-subscription'],
            Product::factory()
                ->subscription()
                ->active()
                ->make([
                    'name' => 'Pro Subscription',
                    'slug' => 'pro-subscription',
                ])
                ->getAttributes(),
        );
    }
}
