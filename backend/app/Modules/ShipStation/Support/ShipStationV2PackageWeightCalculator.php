<?php

namespace App\Modules\ShipStation\Support;

use App\Catalog\Support\G5000IndependentTeamCatalogRegistry;
use App\Catalog\Support\StandardTshirtRebillCatalogBuilder;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Collection;

/**
 * Derives package weight (ounces) for ShipStation V2 create-shipment payloads.
 *
 * BillsMafiaTeam orders: all initial catalog lines contribute {@see BILLS_MAFIA_TEAM_INITIAL_PACKAGE_OUNCES}
 * once per package; other shippable lines use {@see DEFAULT_OUNCES_PER_SHIPPABLE_UNIT} per unit.
 *
 * BigBlueLegends orders: each shippable catalog line (initial, rebill T1, mens/womens) contributes
 * {@see BIG_BLUE_LEGENDS_OUNCES_PER_SHIPPABLE_UNIT} per unit.
 *
 * Qualifying catalog products with stored weight {@see QUALIFYING_CATALOG_WEIGHT_OUNCES} (and variant unit oz)
 * contribute that weight per shippable unit. BillsMafiaTeam initial-package and rebill-role lines are excluded
 * from catalog lookup so existing per-package / 16 oz rebill behavior is preserved.
 */
class ShipStationV2PackageWeightCalculator
{
    public const DEFAULT_OUNCES_PER_SHIPPABLE_UNIT = 16;

    /** Stored CRM catalog weight (oz) that qualifies for per-unit package contribution. */
    public const QUALIFYING_CATALOG_WEIGHT_OUNCES = 6.0;

    /** Fixed package-weight contribution for all BillsMafiaTeam initial catalog lines combined (not per unit or per line). */
    public const BILLS_MAFIA_TEAM_INITIAL_PACKAGE_OUNCES = 6;

    public const BIG_BLUE_LEGENDS_OUNCES_PER_SHIPPABLE_UNIT = 6;

    /**
     * @param  Collection<int, OrderItem>  $shippableItems
     */
    public function totalOuncesForShippableItems(Order $order, Collection $shippableItems): int
    {
        if ($shippableItems->isEmpty()) {
            return 1;
        }

        $order->loadMissing('platformCampaign');

        $storeCode = strtolower((string) ($order->platformCampaign?->store_code ?? ''));
        $isBillsMafiaTeamOrder = $storeCode === G5000IndependentTeamCatalogRegistry::TEAM_BILLS_MAFIA;
        $isBigBlueLegendsOrder = $storeCode === G5000IndependentTeamCatalogRegistry::TEAM_BIG_BLUE;

        $total = 0.0;
        $billsMafiaInitialContributionApplied = false;

        foreach ($shippableItems as $item) {
            if ($isBillsMafiaTeamOrder && $this->isBillsMafiaTeamInitialCatalogLineItem($item)) {
                if (! $billsMafiaInitialContributionApplied) {
                    $total += self::BILLS_MAFIA_TEAM_INITIAL_PACKAGE_OUNCES;
                    $billsMafiaInitialContributionApplied = true;
                }

                continue;
            }

            if ($isBigBlueLegendsOrder && $this->isBigBlueLegendsCatalogLineItem($item)) {
                $total += (float) $item->quantity * self::BIG_BLUE_LEGENDS_OUNCES_PER_SHIPPABLE_UNIT;

                continue;
            }

            $catalogOuncesPerUnit = $this->qualifyingCatalogOuncesPerUnit($item);

            if ($catalogOuncesPerUnit !== null) {
                $total += (float) $item->quantity * $catalogOuncesPerUnit;

                continue;
            }

            $total += (float) $item->quantity * self::DEFAULT_OUNCES_PER_SHIPPABLE_UNIT;
        }

        return max(1, (int) round($total));
    }

    private function isBillsMafiaTeamInitialCatalogLineItem(OrderItem $item): bool
    {
        $item->loadMissing('product');

        $product = $item->product;

        if ($product === null) {
            return false;
        }

        $metadata = is_array($product->metadata) ? $product->metadata : [];

        if (($metadata['offer_type'] ?? null) === 'order_bump') {
            return false;
        }

        if (($metadata['product_role'] ?? null) === StandardTshirtRebillCatalogBuilder::METADATA_PRODUCT_ROLE) {
            return false;
        }

        return ($metadata['campaign_key'] ?? null) === G5000IndependentTeamCatalogRegistry::TEAM_BILLS_MAFIA;
    }

    private function isBigBlueLegendsCatalogLineItem(OrderItem $item): bool
    {
        $item->loadMissing('product');

        $product = $item->product;

        if ($product === null) {
            return false;
        }

        $metadata = is_array($product->metadata) ? $product->metadata : [];

        if (($metadata['offer_type'] ?? null) === 'order_bump') {
            return false;
        }

        return ($metadata['campaign_key'] ?? null) === G5000IndependentTeamCatalogRegistry::TEAM_BIG_BLUE;
    }

    /**
     * Ounces per unit from CRM catalog when the product stores qualifying 6 oz (variant unit oz).
     * Returns null to fall back to campaign defaults (16 oz) for bumps, rebill-role SKUs, or non-6 weights.
     */
    private function qualifyingCatalogOuncesPerUnit(OrderItem $item): ?float
    {
        $item->loadMissing('product.variants');

        $product = $item->product;

        if ($product === null || $product->weight === null) {
            return null;
        }

        $metadata = is_array($product->metadata) ? $product->metadata : [];

        if (($metadata['offer_type'] ?? null) === 'order_bump') {
            return null;
        }

        if (($metadata['product_role'] ?? null) === StandardTshirtRebillCatalogBuilder::METADATA_PRODUCT_ROLE) {
            return null;
        }

        $variant = $product->variants->firstWhere('is_default', true) ?? $product->variants->first();

        if ($variant !== null && filled($variant->weight_unit) && strtolower((string) $variant->weight_unit) !== 'oz') {
            return null;
        }

        $weight = (float) $product->weight;

        if (abs($weight - self::QUALIFYING_CATALOG_WEIGHT_OUNCES) > 0.001) {
            return null;
        }

        return self::QUALIFYING_CATALOG_WEIGHT_OUNCES;
    }
}
