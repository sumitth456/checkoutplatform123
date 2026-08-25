<?php

namespace App\Modules\Product\Import;

final class ProductImportFieldRegistry
{
    /**
     * @return array<string, string>
     */
    public static function fields(): array
    {
        return [
            'sku' => 'SKU',
            'name' => 'Product Name',
            'description' => 'Description',
            'category' => 'Category',
            'brand' => 'Brand',
            'price' => 'Price',
            'compare_price' => 'Compare Price',
            'cost' => 'Cost',
            'weight' => 'Weight',
            'inventory' => 'Inventory',
            'subscription' => 'Subscription',
            'subscription_plan' => 'Subscription Plan',
            'status' => 'Status',
            'product_type' => 'Product Type',
            'image_url' => 'Image URL',
            'variant' => 'Variant',
            'variant_sku' => 'Variant SKU',
            'variant_price' => 'Variant Price',
            'variant_inventory' => 'Variant Inventory',
        ];
    }

    /**
     * @return list<string>
     */
    public static function requiredMappedFields(): array
    {
        return ['sku', 'name', 'price'];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function headerAliases(): array
    {
        return [
            'sku' => ['sku', 'product_sku', 'product sku'],
            'name' => ['name', 'product_name', 'product name', 'title'],
            'description' => ['description', 'desc', 'product_description'],
            'category' => ['category', 'product_category'],
            'brand' => ['brand', 'manufacturer'],
            'price' => ['price', 'base_price', 'product_price'],
            'compare_price' => ['compare_price', 'compare price', 'msrp'],
            'cost' => ['cost', 'cogs'],
            'weight' => ['weight', 'product_weight'],
            'inventory' => ['inventory', 'qty', 'quantity', 'stock'],
            'subscription' => ['subscription', 'is_subscription'],
            'subscription_plan' => ['subscription_plan', 'plan', 'plan_name'],
            'status' => ['status', 'product_status'],
            'product_type' => ['product_type', 'type'],
            'image_url' => ['image_url', 'image', 'image url', 'photo_url'],
            'variant' => ['variant', 'variant_name', 'size', 'option'],
            'variant_sku' => ['variant_sku', 'variant sku'],
            'variant_price' => ['variant_price', 'variant price'],
            'variant_inventory' => ['variant_inventory', 'variant inventory', 'variant_qty'],
        ];
    }
}
