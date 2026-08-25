<?php

namespace App\Modules\LandingPage\Services;

use App\Models\Campaign;

final class LandingPageVariableReplacer
{
    public function __construct(
        private readonly LegacyCheckoutChampVariableResolver $legacy,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function replace(string $html, array $context): string
    {
        $map = array_merge(
            $this->flattenVariables($context),
            $this->aliasesFromPayload($context),
            $this->legacy->resolve($context),
            ['sf-error-message' => ''],
        );

        $html = $this->legacy->replaceMomentTokens($html);
        $html = $this->legacy->stripInactiveHandlebarsBlocks($html);

        return (string) preg_replace_callback(
            '/(\$)?\{\{\s*([^#\/][^}]*?)\s*\}\}/',
            function (array $matches) use ($map): string {
                $key = trim($matches[2]);

                if ($key === 'sf-error-message') {
                    return '';
                }

                if (! array_key_exists($key, $map)) {
                    return $matches[0];
                }

                $value = e((string) $map[$key]);

                return ($matches[1] ?? '') === '$' ? '$'.$value : $value;
            },
            $html,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    private function aliasesFromPayload(array $context): array
    {
        $campaign = (array) ($context['campaign'] ?? []);
        $product = collect($context['products'] ?? [])->first();
        $variant = collect($product['variants'] ?? [])->first();

        return $this->flattenVariables([
            'campaign' => [
                'name' => $campaign['name'] ?? '',
                'description' => $campaign['description'] ?? '',
                'timer' => (string) ($campaign['timer'] ?? ''),
            ],
            'product' => [
                'name' => $product['name'] ?? '',
                'price' => $variant['price'] ?? $product['campaign_price'] ?? '',
                'compare_price' => $variant['compare_at_price'] ?? '',
                'description' => $product['description'] ?? '',
                'image' => collect($variant['images'] ?? $product['images'] ?? [])->first() ?? '',
                'colors' => implode(', ', $product['colors'] ?? []),
                'sizes' => implode(', ', $product['sizes'] ?? []),
            ],
            'shipping' => [
                'price' => (string) ($context['shipping']['price'] ?? $context['shipping']['flat_rate'] ?? '0.00'),
            ],
            'currency' => (string) ($context['currency'] ?? 'USD'),
            'order' => ['total' => '0.00'],
            'customer' => ['first_name' => '', 'last_name' => ''],
            'coupon' => ['discount' => '0.00'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    public function buildContext(Campaign $campaign, array $context): array
    {
        $product = collect($context['products'] ?? [])->first();
        $variant = collect($product['variants'] ?? [])->first();

        return array_merge(
            $this->flattenVariables([
                'campaign' => [
                    'name' => $campaign->name,
                    'description' => $campaign->description,
                    'timer' => (string) ($campaign->metadata['timer'] ?? ''),
                ],
                'product' => [
                    'name' => $product['name'] ?? '',
                    'price' => $variant['price'] ?? $product['campaign_price'] ?? '',
                    'compare_price' => $variant['compare_at_price'] ?? '',
                    'description' => $product['description'] ?? '',
                    'image' => collect($variant['images'] ?? $product['images'] ?? [])->first() ?? '',
                    'colors' => implode(', ', $product['colors'] ?? []),
                    'sizes' => implode(', ', $product['sizes'] ?? []),
                ],
                'shipping' => [
                    'price' => (string) ($context['shipping']['price'] ?? '0.00'),
                ],
                'currency' => (string) ($context['currency'] ?? 'USD'),
                'order' => [
                    'total' => '0.00',
                ],
                'customer' => [
                    'first_name' => '',
                    'last_name' => '',
                ],
                'coupon' => [
                    'discount' => '0.00',
                ],
            ]),
            $this->legacy->resolve($context),
        );
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private function flattenVariables(array $values, string $prefix = ''): array
    {
        $flat = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $flat += $this->flattenVariables($value, $path);
            } else {
                $flat[$path] = (string) $value;
            }
        }

        return $flat;
    }
}
