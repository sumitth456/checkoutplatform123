<?php

namespace Database\Factories;

use App\Models\DunningPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DunningPolicy>
 */
class DunningPolicyFactory extends Factory
{
    protected $model = DunningPolicy::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => 'Default Dunning',
            'is_active' => true,
            'stages' => [
                ['day_offset' => 1, 'channel' => 'email', 'template' => 'payment_failed_day_1'],
            ],
        ];
    }
}
