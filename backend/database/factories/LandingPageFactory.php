<?php

namespace Database\Factories;

use App\Models\LandingPage;
use App\Modules\LandingPage\Enums\LandingPageStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LandingPage> */
class LandingPageFactory extends Factory
{
    protected $model = LandingPage::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'slug' => fake()->unique()->slug(),
            'status' => LandingPageStatus::Draft,
            'content_html' => '<section><h1>Offer</h1><form id="checkout-form"></form></section>',
            'content_css' => 'body { font-family: system-ui; }',
            'content_js' => '',
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => LandingPageStatus::Published,
            'published_at' => now(),
        ]);
    }
}
