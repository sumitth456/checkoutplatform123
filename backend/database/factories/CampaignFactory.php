<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Modules\Campaign\Enums\CampaignStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'uuid' => (string) Str::uuid(),
            'public_id' => strtoupper(Str::random(8)),
            'name' => $name,
            'description' => fake()->sentence(),
            'status' => CampaignStatus::Active,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'metadata' => [],
        ];
    }
}
