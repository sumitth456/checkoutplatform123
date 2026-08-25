<?php

namespace Database\Factories;

use App\Models\RetryPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RetryPolicy>
 */
class RetryPolicyFactory extends Factory
{
    protected $model = RetryPolicy::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => 'Standard Dunning',
            'max_attempts' => 3,
            'retry_offsets' => [1, 3, 5],
        ];
    }
}
