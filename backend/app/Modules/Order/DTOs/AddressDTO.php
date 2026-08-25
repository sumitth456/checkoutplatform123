<?php

namespace App\Modules\Order\DTOs;

readonly class AddressDTO
{
    public function __construct(
        public ?string $line1 = null,
        public ?string $line2 = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $postalCode = null,
        public ?string $country = null,
    ) {}

    public static function fromArray(?array $data): ?self
    {
        if ($data === null || $data === []) {
            return null;
        }

        return new self(
            line1: $data['line1'] ?? $data['address_line1'] ?? null,
            line2: $data['line2'] ?? $data['address_line2'] ?? null,
            city: $data['city'] ?? null,
            state: $data['state'] ?? $data['region'] ?? null,
            postalCode: $data['postal_code'] ?? $data['zip'] ?? null,
            country: isset($data['country']) ? strtoupper((string) $data['country']) : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'line1' => $this->line1,
            'line2' => $this->line2,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postalCode,
            'country' => $this->country,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
