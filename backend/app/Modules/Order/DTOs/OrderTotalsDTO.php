<?php

namespace App\Modules\Order\DTOs;

readonly class OrderTotalsDTO
{
    public function __construct(
        public string $currency,
        public float $subtotal,
        public float $discountTotal,
        public float $taxTotal,
        public float $shippingTotal,
        public float $grandTotal,
        public ?string $countryCode = null,
    ) {}

    public function toOrderAttributes(): array
    {
        return [
            'currency' => $this->currency,
            'country_code' => $this->countryCode,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discountTotal,
            'tax_total' => $this->taxTotal,
            'shipping_total' => $this->shippingTotal,
            'grand_total' => $this->grandTotal,
        ];
    }
}
