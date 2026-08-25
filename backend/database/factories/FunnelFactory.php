<?php

namespace Database\Factories;

use App\Models\Funnel;
use App\Modules\Order\Enums\FunnelStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Funnel>
 */
class FunnelFactory extends Factory
{
    protected $model = Funnel::class;

    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'status' => FunnelStatus::Active,
            'currency' => 'USD',
            'settings' => [],
            'tracking' => [],
            'metadata' => [],
        ];
    }
}
