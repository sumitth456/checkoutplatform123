<?php

namespace App\Modules\Campaign\DTOs;

readonly class UpdateCampaignFunnelMappingDTO
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $attributes = [];

        foreach (['landing_url', 'status', 'sort_order'] as $key) {
            if (array_key_exists($key, $payload)) {
                $attributes[$key] = $payload[$key];
            }
        }

        return new self($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return $this->attributes;
    }
}
