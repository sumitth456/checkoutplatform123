<?php

namespace App\Modules\Campaign\DTOs;

readonly class CreateCampaignDTO
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?string $internalNotes = null,
        public string $status = 'draft',
        public ?int $brandId = null,
        public ?string $storeName = null,
        public ?string $storeCode = null,
        public string $currency = 'USD',
        public string $timezone = 'UTC',
        public ?array $metadata = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            name: (string) $payload['name'],
            description: isset($payload['description']) ? (string) $payload['description'] : null,
            internalNotes: isset($payload['internal_notes']) ? (string) $payload['internal_notes'] : null,
            status: (string) ($payload['status'] ?? 'draft'),
            brandId: isset($payload['brand_id']) ? (int) $payload['brand_id'] : null,
            storeName: isset($payload['store_name']) ? (string) $payload['store_name'] : null,
            storeCode: isset($payload['store_code']) ? (string) $payload['store_code'] : null,
            currency: strtoupper((string) ($payload['currency'] ?? 'USD')),
            timezone: (string) ($payload['timezone'] ?? 'UTC'),
            metadata: isset($payload['metadata']) && is_array($payload['metadata']) ? $payload['metadata'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(string $publicId): array
    {
        return [
            'public_id' => $publicId,
            'name' => $this->name,
            'description' => $this->description,
            'internal_notes' => $this->internalNotes,
            'status' => $this->status,
            'brand_id' => $this->brandId,
            'store_name' => $this->storeName,
            'store_code' => $this->storeCode,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'metadata' => $this->metadata,
        ];
    }
}
