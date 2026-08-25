<?php

namespace App\Modules\LandingPage\Forms;

final class CheckoutFormFieldRegistry
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function defaultCheckoutFields(): array
    {
        return [
            [
                'key' => 'heading_customer',
                'type' => CheckoutFormFieldType::SectionHeading->value,
                'label' => 'Customer details',
                'enabled' => true,
                'sort_order' => 10,
            ],
            [
                'key' => 'customer_name',
                'type' => CheckoutFormFieldType::Text->value,
                'label' => 'Full name',
                'name' => 'customer_name',
                'placeholder' => 'Jane Doe',
                'required' => true,
                'enabled' => true,
                'sort_order' => 20,
            ],
            [
                'key' => 'email',
                'type' => CheckoutFormFieldType::Email->value,
                'label' => 'Email',
                'name' => 'email',
                'placeholder' => 'you@example.com',
                'required' => true,
                'enabled' => true,
                'sort_order' => 30,
            ],
            [
                'key' => 'phone',
                'type' => CheckoutFormFieldType::Tel->value,
                'label' => 'Phone',
                'name' => 'phone',
                'placeholder' => '+1 555 0100',
                'required' => false,
                'enabled' => true,
                'sort_order' => 40,
            ],
            [
                'key' => 'heading_product',
                'type' => CheckoutFormFieldType::SectionHeading->value,
                'label' => 'Product',
                'enabled' => true,
                'sort_order' => 50,
            ],
            [
                'key' => 'variant_uuid',
                'type' => CheckoutFormFieldType::Select->value,
                'label' => 'Product variant',
                'name' => 'variant_uuid',
                'required' => true,
                'enabled' => true,
                'sort_order' => 60,
                'options_source' => 'campaign_variants',
            ],
            [
                'key' => 'quantity',
                'type' => CheckoutFormFieldType::Number->value,
                'label' => 'Quantity',
                'name' => 'quantity',
                'required' => true,
                'enabled' => true,
                'sort_order' => 70,
                'default' => 1,
                'min' => 1,
            ],
            [
                'key' => 'heading_shipping',
                'type' => CheckoutFormFieldType::SectionHeading->value,
                'label' => 'Shipping address',
                'enabled' => true,
                'sort_order' => 80,
            ],
            [
                'key' => 'shipping_address',
                'type' => CheckoutFormFieldType::Text->value,
                'label' => 'Address line 1',
                'name' => 'shipping_address',
                'required' => true,
                'enabled' => true,
                'sort_order' => 90,
            ],
            [
                'key' => 'shipping_address_line2',
                'type' => CheckoutFormFieldType::Text->value,
                'label' => 'Address line 2',
                'name' => 'shipping_address_line2',
                'required' => false,
                'enabled' => true,
                'sort_order' => 100,
            ],
            [
                'key' => 'shipping_city',
                'type' => CheckoutFormFieldType::Text->value,
                'label' => 'City',
                'name' => 'shipping_city',
                'required' => true,
                'enabled' => true,
                'sort_order' => 110,
            ],
            [
                'key' => 'shipping_state',
                'type' => CheckoutFormFieldType::Text->value,
                'label' => 'State / Province',
                'name' => 'shipping_state',
                'required' => false,
                'enabled' => true,
                'sort_order' => 120,
            ],
            [
                'key' => 'shipping_postal_code',
                'type' => CheckoutFormFieldType::Text->value,
                'label' => 'Postal code',
                'name' => 'shipping_postal_code',
                'required' => true,
                'enabled' => true,
                'sort_order' => 130,
            ],
            [
                'key' => 'shipping_country',
                'type' => CheckoutFormFieldType::Text->value,
                'label' => 'Country (2-letter)',
                'name' => 'shipping_country',
                'required' => true,
                'enabled' => true,
                'sort_order' => 140,
                'default' => 'US',
            ],
            [
                'key' => 'heading_billing',
                'type' => CheckoutFormFieldType::SectionHeading->value,
                'label' => 'Billing address',
                'enabled' => true,
                'sort_order' => 150,
            ],
            [
                'key' => 'billing_address',
                'type' => CheckoutFormFieldType::Text->value,
                'label' => 'Address line 1',
                'name' => 'billing_address',
                'required' => false,
                'enabled' => true,
                'sort_order' => 160,
            ],
            [
                'key' => 'billing_city',
                'type' => CheckoutFormFieldType::Text->value,
                'label' => 'City',
                'name' => 'billing_city',
                'required' => false,
                'enabled' => true,
                'sort_order' => 170,
            ],
            [
                'key' => 'billing_state',
                'type' => CheckoutFormFieldType::Text->value,
                'label' => 'State / Province',
                'name' => 'billing_state',
                'required' => false,
                'enabled' => true,
                'sort_order' => 180,
            ],
            [
                'key' => 'billing_postal_code',
                'type' => CheckoutFormFieldType::Text->value,
                'label' => 'Postal code',
                'name' => 'billing_postal_code',
                'required' => false,
                'enabled' => true,
                'sort_order' => 190,
            ],
            [
                'key' => 'billing_country',
                'type' => CheckoutFormFieldType::Text->value,
                'label' => 'Country (2-letter)',
                'name' => 'billing_country',
                'required' => false,
                'enabled' => true,
                'sort_order' => 200,
                'default' => 'US',
            ],
            [
                'key' => 'coupon_code',
                'type' => CheckoutFormFieldType::Text->value,
                'label' => 'Coupon code',
                'name' => 'coupon_code',
                'required' => false,
                'enabled' => true,
                'sort_order' => 210,
            ],
            [
                'key' => 'payment_token',
                'type' => CheckoutFormFieldType::PaymentToken->value,
                'label' => 'Payment token',
                'name' => 'payment_token',
                'required' => true,
                'enabled' => true,
                'sort_order' => 220,
                'help' => 'Stripe Elements or test token placeholder.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultSettings(): array
    {
        return [
            'submit_label' => 'Complete checkout',
            'auto_inject' => true,
            'form_css_class' => 'lp-checkout-form',
        ];
    }
}
