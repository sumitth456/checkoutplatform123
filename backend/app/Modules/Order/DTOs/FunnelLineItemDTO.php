<?php

namespace App\Modules\Order\DTOs;

readonly class FunnelLineItemDTO
{
    public function __construct(
        public int $quantity,
        public ?string $productUuid = null,
        public ?string $productSku = null,
        public ?string $variantUuid = null,
        public ?string $variantSku = null,
        public ?string $size = null,
        public ?string $color = null,
        public ?array $options = null,
        public ?string $orderBumpUuid = null,
        public ?int $campaignProductLineId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $variant = $data['variant'] ?? [];

        if (! is_array($variant)) {
            $variant = ['size' => $variant];
        }

        $options = $data['options'] ?? $variant['options'] ?? null;

        if (! is_array($options)) {
            $options = null;
        }

        return new self(
            quantity: (int) $data['quantity'],
            productUuid: $data['product_id'] ?? $data['product_uuid'] ?? null,
            productSku: $data['product_sku'] ?? $data['sku'] ?? null,
            variantUuid: $data['variant_uuid'] ?? $variant['uuid'] ?? null,
            variantSku: $data['variant_sku'] ?? $variant['sku'] ?? null,
            size: $data['size'] ?? $variant['size'] ?? ($options['size'] ?? null),
            color: $data['color'] ?? $variant['color'] ?? ($options['color'] ?? null),
            options: $options,
            orderBumpUuid: $data['order_bump_uuid'] ?? null,
            campaignProductLineId: isset($data['campaign_product_line_id']) ? (int) $data['campaign_product_line_id'] : null,
        );
    }
}
