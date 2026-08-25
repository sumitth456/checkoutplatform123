<?php

namespace App\Modules\Email\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Subscription;
use App\Modules\Admin\Services\PlatformConfigResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

final class EmailVariableResolver
{
    public function __construct(
        private readonly PlatformConfigResolver $platform,
    ) {}

    /**
     * @return array<string, string>
     */
    public function resolve(?Model $context = null): array
    {
        $variables = $this->baseVariables();

        if ($context instanceof Order) {
            $variables = array_merge($variables, $this->orderVariables($context));
        }

        if ($context instanceof Subscription) {
            $variables = array_merge($variables, $this->subscriptionVariables($context));
        }

        if ($context instanceof Customer) {
            $variables = array_merge($variables, $this->customerVariables($context));
        }

        return $variables;
    }

    /**
     * @return array<string, string>
     */
    private function baseVariables(): array
    {
        $settings = $this->platform->settings();
        $logoUrl = config('email.company_logo_url') ?: url('/images/logo.png');

        return [
            'company.name' => $this->platform->storeName(),
            'company.logo_url' => (string) $logoUrl,
            'company.support_email' => (string) ($this->platform->supportEmail() ?? config('mail.from.address')),
            'support.email' => (string) ($this->platform->supportEmail() ?? config('mail.from.address')),
            'support.contact' => (string) ($this->platform->supportEmail() ?? config('mail.from.address')),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function customerVariables(Customer $customer): array
    {
        return [
            'customer.first_name' => (string) ($customer->first_name ?? ''),
            'customer.last_name' => (string) ($customer->last_name ?? ''),
            'customer.email' => (string) ($customer->email ?? ''),
            'customer.phone' => (string) ($customer->phone ?? ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function orderVariables(Order $order): array
    {
        $order->loadMissing(['customer', 'items.product', 'items.variant', 'subscriptions', 'paymentGateway']);

        $customer = $order->customer;
        $currency = strtoupper((string) ($order->currency ?: $this->platform->defaultCurrency()));
        $metadata = $order->metadata ?? [];
        $subscription = $order->subscriptions->first();

        $variables = array_merge($this->baseVariables(), [
            'customer.first_name' => (string) ($customer?->first_name ?? $this->firstNameFromAddress($order->billing_address)),
            'customer.last_name' => (string) ($customer?->last_name ?? $this->lastNameFromAddress($order->billing_address)),
            'customer.email' => (string) ($order->customer_email ?? $customer?->email ?? ''),
            'customer.phone' => (string) ($order->customer_phone ?? $customer?->phone ?? ''),
            'order.number' => (string) $order->order_number,
            'order.date' => optional($order->placed_at)->format('M j, Y g:i A') ?? now()->format('M j, Y g:i A'),
            'order.total' => $this->money($order->grand_total, $currency),
            'order.tax' => $this->money($order->tax_total, $currency),
            'order.shipping' => $this->money($order->shipping_total, $currency),
            'order.discount' => $this->money($order->discount_total, $currency),
            'order.subtotal' => $this->money($order->subtotal, $currency),
            'order.status' => (string) ($order->status?->value ?? $order->status ?? ''),
            'order.payment_method' => (string) ($order->paymentGateway?->name ?? $metadata['payment_method_label'] ?? 'Card'),
            'order.billing_address' => $this->formatAddress($order->billing_address),
            'order.shipping_address' => $this->formatAddress($order->shipping_address),
            'order.items_html' => $this->renderItemsHtml($order, $currency),
            'order.items_text' => $this->renderItemsText($order, $currency),
            'order.invoice_url' => url(config('email.invoice_base_url').'/'.$order->uuid.'/invoice'),
            'tracking.number' => (string) ($metadata['tracking_number'] ?? ''),
            'tracking.url' => (string) ($metadata['tracking_url'] ?? ''),
            'carrier' => (string) ($metadata['carrier'] ?? ''),
            'warehouse' => (string) ($metadata['warehouse'] ?? ''),
            'thank_you_message' => 'Thank you for your order!',
        ]);

        $firstItem = $order->items->first();

        if ($firstItem !== null) {
            $options = $firstItem->options ?? [];
            $itemMeta = $firstItem->metadata ?? [];

            $variables = array_merge($variables, [
                'product.name' => (string) ($firstItem->name ?? $firstItem->product?->name ?? ''),
                'product.variant' => (string) ($firstItem->variant?->name ?? ($options['variant'] ?? '')),
                'product.color' => (string) ($options['color'] ?? $itemMeta['color'] ?? ''),
                'product.size' => (string) ($options['size'] ?? $itemMeta['size'] ?? ''),
            ]);
        }

        if ($subscription !== null) {
            $variables = array_merge($variables, $this->subscriptionVariables($subscription));
        }

        return $variables;
    }

    /**
     * @return array<string, string>
     */
    private function subscriptionVariables(Subscription $subscription): array
    {
        $subscription->loadMissing(['customer', 'product', 'variant', 'plan']);

        $customer = $subscription->customer;
        $currency = strtoupper((string) ($subscription->currency ?: $this->platform->defaultCurrency()));

        return array_merge($this->customerVariables($customer), [
            'subscription.id' => (string) ($subscription->uuid ?? $subscription->id),
            'subscription.next_rebill' => optional($subscription->next_billing_at)->format('M j, Y') ?? '',
            'subscription.amount' => $this->money($subscription->amount, $currency),
            'subscription.status' => (string) ($subscription->status?->value ?? $subscription->status ?? ''),
            'product.name' => (string) ($subscription->product?->name ?? ''),
            'product.variant' => (string) ($subscription->variant?->name ?? ''),
        ]);
    }

    private function money(float|string|null $amount, string $currency): string
    {
        return Number::currency((float) $amount, in: $currency);
    }

    /**
     * @param  array<string, mixed>|null  $address
     */
    private function formatAddress(?array $address): string
    {
        if ($address === null || $address === []) {
            return '';
        }

        $lines = array_filter([
            trim(($address['first_name'] ?? '').' '.($address['last_name'] ?? '')),
            $address['company'] ?? null,
            $address['address1'] ?? $address['line1'] ?? null,
            $address['address2'] ?? $address['line2'] ?? null,
            trim(($address['city'] ?? '').', '.($address['state'] ?? $address['province'] ?? '').' '.($address['postal_code'] ?? $address['zip'] ?? '')),
            $address['country'] ?? $address['country_code'] ?? null,
        ]);

        return implode('<br>', array_map('e', $lines));
    }

    private function renderItemsHtml(Order $order, string $currency): string
    {
        if ($order->items->isEmpty()) {
            return '<p>No items</p>';
        }

        $rows = $order->items->map(function ($item) use ($currency): string {
            $options = $item->options ?? [];
            $meta = $item->metadata ?? [];
            $variant = e($item->variant?->name ?? ($options['variant'] ?? ''));
            $color = e($options['color'] ?? $meta['color'] ?? '');
            $size = e($options['size'] ?? $meta['size'] ?? '');

            return '<tr>'
                .'<td style="padding:8px;border-bottom:1px solid #eee;">'.e($item->name).'<br><small>'.$variant
                .($color !== '' ? ' · Color: '.$color : '')
                .($size !== '' ? ' · Size: '.$size : '')
                .'</small></td>'
                .'<td style="padding:8px;border-bottom:1px solid #eee;text-align:center;">'.(int) $item->quantity.'</td>'
                .'<td style="padding:8px;border-bottom:1px solid #eee;text-align:right;">'.$this->money($item->total, $currency).'</td>'
                .'</tr>';
        })->implode('');

        return '<table style="width:100%;border-collapse:collapse;">'
            .'<thead><tr>'
            .'<th style="text-align:left;padding:8px;border-bottom:2px solid #ddd;">Product</th>'
            .'<th style="text-align:center;padding:8px;border-bottom:2px solid #ddd;">Qty</th>'
            .'<th style="text-align:right;padding:8px;border-bottom:2px solid #ddd;">Total</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    private function renderItemsText(Order $order, string $currency): string
    {
        return $order->items->map(function ($item) use ($currency): string {
            return sprintf(
                '%s x%d — %s',
                $item->name,
                (int) $item->quantity,
                $this->money($item->total, $currency),
            );
        })->implode("\n");
    }

    /**
     * @param  array<string, mixed>|null  $address
     */
    private function firstNameFromAddress(?array $address): string
    {
        return (string) ($address['first_name'] ?? '');
    }

    /**
     * @param  array<string, mixed>|null  $address
     */
    private function lastNameFromAddress(?array $address): string
    {
        return (string) ($address['last_name'] ?? '');
    }
}
