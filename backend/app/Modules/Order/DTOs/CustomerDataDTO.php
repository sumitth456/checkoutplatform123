<?php

namespace App\Modules\Order\DTOs;

readonly class CustomerDataDTO
{
    public function __construct(
        public string $email,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $phone,
        public ?string $company = null,
        public bool $acceptsMarketing = false,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            email: strtolower(trim((string) $data['email'])),
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            phone: $data['phone'] ?? null,
            company: $data['company'] ?? null,
            acceptsMarketing: (bool) ($data['accepts_marketing'] ?? false),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'email' => $this->email,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'phone' => $this->phone,
            'company' => $this->company,
            'accepts_marketing' => $this->acceptsMarketing,
        ], fn (mixed $value): bool => $value !== null && $value !== false);
    }
}
