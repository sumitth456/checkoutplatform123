<?php

namespace App\Modules\Order\DTOs;

readonly class CreateFunnelOrderDTO
{
    /**
     * @param  list<FunnelLineItemDTO>  $lineItems
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public CustomerDataDTO $customer,
        public array $lineItems,
        public RequestContextDTO $context,
        public OrderAttributionDTO $attribution,
        public array $metadata = [],
        public ?AddressDTO $billingAddress = null,
        public ?AddressDTO $shippingAddress = null,
        public ?string $notes = null,
        public ?string $currency = null,
        public ?string $countryCode = null,
        public float $discountTotal = 0,
        public float $taxTotal = 0,
        public float $shippingTotal = 0,
        public ?int $customerId = null,
        public ?string $funnelUuid = null,
        public ?string $funnelSlug = null,
        public ?string $couponCode = null,
        public ?int $couponId = null,
    ) {}

    public static function fromValidated(array $data, RequestContextDTO $context): self
    {
        $metadata = $data['metadata'] ?? [];

        if (isset($data['subscription_plan_uuid']) && ! isset($metadata['subscription_plan_uuid'])) {
            $metadata['subscription_plan_uuid'] = $data['subscription_plan_uuid'];
        }

        if (isset($data['fulfillment_delay_days']) && ! isset($metadata['fulfillment_delay_days'])) {
            $metadata['fulfillment_delay_days'] = $data['fulfillment_delay_days'];
        }

        $items = collect($data['items'] ?? [])
            ->map(fn (array $item): FunnelLineItemDTO => FunnelLineItemDTO::fromArray($item))
            ->all();

        return new self(
            customer: CustomerDataDTO::fromArray($data['customer'] ?? []),
            lineItems: $items,
            context: $context,
            attribution: OrderAttributionDTO::fromValidated($data),
            metadata: $metadata,
            billingAddress: AddressDTO::fromArray($data['billing_address'] ?? null),
            shippingAddress: AddressDTO::fromArray($data['shipping_address'] ?? null),
            notes: $data['notes'] ?? null,
            currency: isset($data['currency']) ? strtoupper((string) $data['currency']) : null,
            countryCode: isset($data['country_code']) ? strtoupper((string) $data['country_code']) : null,
            discountTotal: (float) ($data['discount_total'] ?? 0),
            taxTotal: (float) ($data['tax_total'] ?? 0),
            shippingTotal: (float) ($data['shipping_total'] ?? 0),
            customerId: isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            funnelUuid: $data['funnel_uuid'] ?? null,
            funnelSlug: $data['funnel_slug'] ?? null,
            couponCode: isset($data['coupon_code']) ? strtoupper(trim((string) $data['coupon_code'])) : null,
            couponId: isset($data['coupon_id']) ? (int) $data['coupon_id'] : null,
        );
    }
}
