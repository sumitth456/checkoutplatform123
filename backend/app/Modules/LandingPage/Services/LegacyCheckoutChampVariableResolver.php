<?php

namespace App\Modules\LandingPage\Services;

use App\Modules\Admin\Services\PlatformConfigResolver;
use App\Modules\Campaign\Services\CampaignVariantMatrixService;
use Carbon\Carbon;

final class LegacyCheckoutChampVariableResolver
{
    public function __construct(
        private readonly PlatformConfigResolver $platform,
        private readonly CampaignVariantMatrixService $variantMatrix,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    public function resolve(array $context): array
    {
        $campaign = (array) ($context['campaign'] ?? []);
        $products = collect($context['products'] ?? []);
        $product = $products->first() ?? [];
        $mensProduct = $this->resolveAudienceProduct($products, 'mens') ?? $product;
        $womensProduct = $this->resolveAudienceProduct($products, 'womens') ?? $products->skip(1)->first() ?? $product;
        $variants = collect($product['variants'] ?? []);
        $firstVariant = $variants->first() ?? [];
        $price = (string) ($firstVariant['price'] ?? $product['campaign_price'] ?? '0.00');
        $settings = $this->platform->settings();

        $map = [
            'campaign-id' => (string) ($campaign['uuid'] ?? ''),
            'campaign.uuid' => (string) ($campaign['uuid'] ?? ''),
            'productName' => (string) ($product['name'] ?? ''),
            'prd1-name' => (string) ($product['name'] ?? ''),
            'prd1-prc' => $price,
            'corpName' => (string) ($campaign['store_name'] ?? $settings->store_name ?? $this->platform->storeName()),
            'brandName' => (string) ($campaign['store_name'] ?? $settings->store_name ?? $this->platform->storeName()),
            'csEmail' => (string) ($settings->support_email ?? $this->platform->supportEmail() ?? 'support@example.com'),
            'csPhone' => (string) (($campaign['metadata']['support_phone'] ?? null) ?: '1-800-555-0100'),
            'csHours' => (string) (($campaign['metadata']['support_hours'] ?? null) ?: 'Mon–Fri 9am–5pm ET'),
            'logo-main' => (string) (($campaign['metadata']['logo'] ?? null) ?: 'logo.webp'),
            'firstName' => '',
            'lastName' => '',
            'email' => '',
            'phone' => '',
        ];

        $map += $this->resolveVariantIdTokens($mensProduct, $womensProduct);
        $map += $this->resolveMerchandisingTokens($context, $price);

        return $map;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $products
     * @return array<string, mixed>|null
     */
    private function resolveAudienceProduct($products, string $audience): ?array
    {
        $match = $products->first(function (array $product) use ($audience): bool {
            $value = strtolower((string) ($product['audience'] ?? ''));

            return $value === $audience;
        });

        if ($match !== null) {
            return $match;
        }

        $needle = strtoupper($audience === 'mens' ? 'MENS' : 'WOMENS');

        return $products->first(function (array $product) use ($needle): bool {
            return str_contains(strtoupper((string) ($product['sku'] ?? '')), $needle);
        });
    }

    /**
     * @return array<string, string>
     */
    private function resolveVariantIdTokens(array $mensProduct, array $womensProduct): array
    {
        $tokens = [];
        $sizes = ['s' => 'S', 'm' => 'M', 'l' => 'L', 'xl' => 'XL', '2xl' => 'XXL', '3xl' => '3XL', '4xl' => '4XL'];
        $colors = ['white', 'black'];

        foreach (['m' => $mensProduct, 'w' => $womensProduct] as $gender => $product) {
            $variants = collect($product['variants'] ?? []);

            foreach ($colors as $color) {
                foreach ($sizes as $sizeKey => $sizeLabel) {
                    $token = "{$gender}-prd1-{$color}-{$sizeKey}-id";
                    $tokens[$token] = $this->findVariantUuid($variants, $color, $sizeLabel);
                }
            }

            foreach ($sizes as $sizeKey => $sizeLabel) {
                $token = "{$gender}-prd1-{$sizeKey}-id";
                $tokens[$token] = $this->findVariantUuid($variants, null, $sizeLabel);
            }
        }

        return $tokens;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $variants
     */
    private function findVariantUuid($variants, ?string $color, string $sizeLabel): string
    {
        $normalizedSize = $this->normalizeSizeLabel($sizeLabel);

        $match = $variants->first(function (array $variant) use ($color, $normalizedSize): bool {
            $variantColor = isset($variant['color']) ? strtolower((string) $variant['color']) : null;
            $variantSize = $this->normalizeSizeLabel((string) ($variant['size'] ?? $variant['name'] ?? ''));

            if ($color !== null && $variantColor !== null && $variantColor !== strtolower($color)) {
                return false;
            }

            return $variantSize === $normalizedSize;
        });

        if ($match !== null) {
            return (string) ($match['uuid'] ?? '');
        }

        $fallback = $variants->first(function (array $variant) use ($normalizedSize): bool {
            return $this->normalizeSizeLabel((string) ($variant['size'] ?? $variant['name'] ?? '')) === $normalizedSize;
        });

        if ($fallback !== null) {
            return (string) ($fallback['uuid'] ?? '');
        }

        $ladder = ['4XL', '3XL', '2XL', 'XL', 'Large', 'Medium', 'Small'];
        $requestedIndex = array_search($normalizedSize, $ladder, true);

        if ($requestedIndex !== false) {
            for ($i = $requestedIndex; $i < count($ladder); $i++) {
                $candidate = $variants->first(function (array $variant) use ($ladder, $i): bool {
                    return $this->normalizeSizeLabel((string) ($variant['size'] ?? $variant['name'] ?? '')) === $ladder[$i];
                });

                if ($candidate !== null) {
                    return (string) ($candidate['uuid'] ?? '');
                }
            }
        }

        return (string) ($variants->first()['uuid'] ?? '');
    }

    private function normalizeSizeLabel(string $size): string
    {
        $normalized = strtolower(trim($size));

        return match ($normalized) {
            's', 'sm', 'small' => 'S',
            'm', 'md', 'medium' => 'M',
            'l', 'lg', 'large' => 'L',
            'xl' => 'XL',
            'xxl', '2xl', '2x' => 'XXL',
            'xxxl', '3xl', '3x' => '3XL',
            'xxxxl', '4xl', '4x' => '4XL',
            default => strtoupper(trim($size)),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    private function resolveMerchandisingTokens(array $context, string $fallbackPrice): array
    {
        $bumps = collect($context['merchandising']['order_bumps'] ?? []);
        $firstBump = $bumps->first();
        $journeyBump = $bumps->first(fn (array $b): bool => str_contains(strtolower((string) ($b['product']['name'] ?? '')), 'shield'))
            ?? $bumps->first();
        $agedBump = $bumps->first(fn (array $b): bool => str_contains(strtolower((string) ($b['product']['name'] ?? '')), 'priority'))
            ?? $bumps->skip(1)->first()
            ?? $firstBump;

        return [
            'journey-id' => (string) ($journeyBump['uuid'] ?? ''),
            'journey-name' => (string) ($journeyBump['product']['name'] ?? 'Order Bump'),
            'journey-prc' => $this->formatBumpPrice($journeyBump, $fallbackPrice),
            'aged-id' => (string) ($agedBump['uuid'] ?? ''),
            'aged-prc' => $this->formatBumpPrice($agedBump, '9.99'),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $bump
     */
    private function formatBumpPrice(?array $bump, string $fallback): string
    {
        if ($bump === null || $bump['bump_price'] === null) {
            return $fallback;
        }

        return number_format((float) $bump['bump_price'], 2, '.', '');
    }

    public function replaceMomentTokens(string $html): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*moment\s+(.*?)\s*\}\}/',
            function (array $matches): string {
                $parts = preg_split("/['\"]\s+['\"]/", trim($matches[1], " '\"")) ?: [];
                $offset = trim((string) ($parts[0] ?? ''));
                $format = trim((string) ($parts[1] ?? 'YYYY'), " '\"");

                $date = Carbon::now();

                if ($offset !== '' && $offset !== 'YYYY') {
                    $date = match ($offset) {
                        'next year' => $date->copy()->addYear(),
                        'two years' => $date->copy()->addYears(2),
                        'three years' => $date->copy()->addYears(3),
                        'four years' => $date->copy()->addYears(4),
                        'five years' => $date->copy()->addYears(5),
                        'six years' => $date->copy()->addYears(6),
                        'seven years' => $date->copy()->addYears(7),
                        'eight years' => $date->copy()->addYears(8),
                        'nine years' => $date->copy()->addYears(9),
                        'ten years' => $date->copy()->addYears(10),
                        default => $date,
                    };
                }

                return $format === 'YYYY' ? $date->format('Y') : $date->format($format);
            },
            $html,
        );
    }

    public function stripInactiveHandlebarsBlocks(string $html): string
    {
        $html = (string) preg_replace('/\{\{#if\s+sf-error-message\s*\}\}.*?\{\{\/if\s*\}\}/is', '', $html);
        $html = (string) preg_replace('/\{\{#if\s+[^}]+\}\}/', '', $html);
        $html = (string) preg_replace('/\{\{\/if\s*\}\}/', '', $html);
        $html = (string) preg_replace('/<div[^>]*id=["\']imatau["\'][^>]*>\s*<\/div>/i', '', $html);

        return $html;
    }
}
