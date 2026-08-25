<?php

namespace App\Modules\Campaign\Services;

use App\Models\Campaign;
use App\Models\CampaignDownsell;
use App\Models\CampaignOrderBump;
use App\Models\CampaignUpsell;
use App\Modules\Campaign\Enums\CampaignDiscountType;
use App\Modules\Campaign\Enums\CampaignOfferPosition;
use App\Modules\Campaign\Enums\CampaignOfferTrigger;
use App\Modules\Campaign\Enums\CampaignOrderBumpDisplayRule;
use Illuminate\Support\Facades\DB;

final class CampaignMerchandisingService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createUpsell(Campaign $campaign, array $attributes): CampaignUpsell
    {
        return $campaign->upsells()->create($this->normalizeUpsellAttributes($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateUpsell(CampaignUpsell $upsell, array $attributes): CampaignUpsell
    {
        $upsell->update($this->normalizeUpsellAttributes($attributes, partial: true));

        return $upsell->fresh(['product']);
    }

    public function deleteUpsell(CampaignUpsell $upsell): void
    {
        $upsell->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createDownsell(Campaign $campaign, array $attributes): CampaignDownsell
    {
        return $campaign->downsells()->create($this->normalizeDownsellAttributes($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateDownsell(CampaignDownsell $downsell, array $attributes): CampaignDownsell
    {
        $downsell->update($this->normalizeDownsellAttributes($attributes, partial: true));

        return $downsell->fresh(['product']);
    }

    public function deleteDownsell(CampaignDownsell $downsell): void
    {
        $downsell->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createOrderBump(Campaign $campaign, array $attributes): CampaignOrderBump
    {
        return $campaign->orderBumps()->create($this->normalizeOrderBumpAttributes($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateOrderBump(CampaignOrderBump $orderBump, array $attributes): CampaignOrderBump
    {
        $orderBump->update($this->normalizeOrderBumpAttributes($attributes, partial: true));

        return $orderBump->fresh(['product']);
    }

    public function deleteOrderBump(CampaignOrderBump $orderBump): void
    {
        $orderBump->delete();
    }

    /**
     * @return array{upsells: list<array<string, mixed>>, downsells: list<array<string, mixed>>, order_bumps: list<array<string, mixed>>}
     */
    public function checkoutPayload(Campaign $campaign): array
    {
        $campaign->loadMissing([
            'upsells.product',
            'downsells.product',
            'orderBumps.product',
        ]);

        return [
            'upsells' => $campaign->upsells
                ->sortByDesc('priority')
                ->sortBy('sort_order')
                ->values()
                ->map(fn (CampaignUpsell $row): array => $this->serializeUpsell($row))
                ->all(),
            'downsells' => $campaign->downsells
                ->sortByDesc('priority')
                ->sortBy('sort_order')
                ->values()
                ->map(fn (CampaignDownsell $row): array => $this->serializeDownsell($row))
                ->all(),
            'order_bumps' => $campaign->orderBumps
                ->sortByDesc('priority')
                ->sortBy('sort_order')
                ->values()
                ->map(fn (CampaignOrderBump $row): array => $this->serializeOrderBump($row))
                ->all(),
        ];
    }

    /**
     * @return array{upsells: int, downsells: int, order_bumps: int}
     */
    public function copyMerchandising(Campaign $source, Campaign $target): array
    {
        $source->loadMissing(['upsells', 'downsells', 'orderBumps']);

        return DB::transaction(function () use ($source, $target): array {
            $counts = ['upsells' => 0, 'downsells' => 0, 'order_bumps' => 0];

            foreach ($source->upsells as $row) {
                $target->upsells()->create($this->normalizeUpsellAttributes([
                    'product_id' => $row->product_id,
                    'priority' => $row->priority,
                    'sort_order' => $row->sort_order,
                    'trigger' => $row->trigger->value,
                    'trigger_config' => $row->trigger_config,
                    'position' => $row->position->value,
                    'discount_type' => $row->discount_type->value,
                    'discount_value' => $row->discount_value,
                    'is_active' => $row->is_active,
                ]));
                $counts['upsells']++;
            }

            foreach ($source->downsells as $row) {
                $target->downsells()->create($this->normalizeDownsellAttributes([
                    'product_id' => $row->product_id,
                    'priority' => $row->priority,
                    'sort_order' => $row->sort_order,
                    'trigger' => $row->trigger->value,
                    'trigger_config' => $row->trigger_config,
                    'discount_type' => $row->discount_type->value,
                    'discount_value' => $row->discount_value,
                    'is_active' => $row->is_active,
                ]));
                $counts['downsells']++;
            }

            foreach ($source->orderBumps as $row) {
                $target->orderBumps()->create($this->normalizeOrderBumpAttributes([
                    'product_id' => $row->product_id,
                    'priority' => $row->priority,
                    'sort_order' => $row->sort_order,
                    'bump_price' => $row->bump_price,
                    'position' => $row->position->value,
                    'display_rule' => $row->display_rule->value,
                    'display_rule_config' => $row->display_rule_config,
                    'is_active' => $row->is_active,
                ]));
                $counts['order_bumps']++;
            }

            return $counts;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeUpsellAttributes(array $attributes, bool $partial = false): array
    {
        $normalized = [];

        if (! $partial || array_key_exists('product_id', $attributes)) {
            $normalized['product_id'] = (int) $attributes['product_id'];
        }

        if (! $partial || array_key_exists('priority', $attributes)) {
            $normalized['priority'] = (int) ($attributes['priority'] ?? 100);
        }

        if (! $partial || array_key_exists('sort_order', $attributes)) {
            $normalized['sort_order'] = (int) ($attributes['sort_order'] ?? 0);
        }

        if (! $partial || array_key_exists('trigger', $attributes)) {
            $normalized['trigger'] = CampaignOfferTrigger::from((string) ($attributes['trigger'] ?? CampaignOfferTrigger::Always->value));
        }

        if (! $partial || array_key_exists('trigger_config', $attributes)) {
            $normalized['trigger_config'] = $attributes['trigger_config'] ?? null;
        }

        if (! $partial || array_key_exists('position', $attributes)) {
            $normalized['position'] = CampaignOfferPosition::from((string) ($attributes['position'] ?? CampaignOfferPosition::PostCheckout->value));
        }

        if (! $partial || array_key_exists('discount_type', $attributes)) {
            $normalized['discount_type'] = CampaignDiscountType::from((string) ($attributes['discount_type'] ?? CampaignDiscountType::None->value));
        }

        if (! $partial || array_key_exists('discount_value', $attributes)) {
            $normalized['discount_value'] = $attributes['discount_value'] ?? null;
        }

        if (! $partial || array_key_exists('is_active', $attributes)) {
            $normalized['is_active'] = (bool) ($attributes['is_active'] ?? true);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeDownsellAttributes(array $attributes, bool $partial = false): array
    {
        $normalized = [];

        if (! $partial || array_key_exists('product_id', $attributes)) {
            $normalized['product_id'] = (int) $attributes['product_id'];
        }

        if (! $partial || array_key_exists('priority', $attributes)) {
            $normalized['priority'] = (int) ($attributes['priority'] ?? 100);
        }

        if (! $partial || array_key_exists('sort_order', $attributes)) {
            $normalized['sort_order'] = (int) ($attributes['sort_order'] ?? 0);
        }

        if (! $partial || array_key_exists('trigger', $attributes)) {
            $normalized['trigger'] = CampaignOfferTrigger::from((string) ($attributes['trigger'] ?? CampaignOfferTrigger::DeclinedUpsell->value));
        }

        if (! $partial || array_key_exists('trigger_config', $attributes)) {
            $normalized['trigger_config'] = $attributes['trigger_config'] ?? null;
        }

        if (! $partial || array_key_exists('discount_type', $attributes)) {
            $normalized['discount_type'] = CampaignDiscountType::from((string) ($attributes['discount_type'] ?? CampaignDiscountType::None->value));
        }

        if (! $partial || array_key_exists('discount_value', $attributes)) {
            $normalized['discount_value'] = $attributes['discount_value'] ?? null;
        }

        if (! $partial || array_key_exists('is_active', $attributes)) {
            $normalized['is_active'] = (bool) ($attributes['is_active'] ?? true);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeOrderBumpAttributes(array $attributes, bool $partial = false): array
    {
        $normalized = [];

        if (! $partial || array_key_exists('product_id', $attributes)) {
            $normalized['product_id'] = (int) $attributes['product_id'];
        }

        if (! $partial || array_key_exists('priority', $attributes)) {
            $normalized['priority'] = (int) ($attributes['priority'] ?? 100);
        }

        if (! $partial || array_key_exists('sort_order', $attributes)) {
            $normalized['sort_order'] = (int) ($attributes['sort_order'] ?? 0);
        }

        if (! $partial || array_key_exists('bump_price', $attributes)) {
            $normalized['bump_price'] = $attributes['bump_price'] ?? null;
        }

        if (! $partial || array_key_exists('position', $attributes)) {
            $normalized['position'] = CampaignOfferPosition::from((string) ($attributes['position'] ?? CampaignOfferPosition::PreCheckout->value));
        }

        if (! $partial || array_key_exists('display_rule', $attributes)) {
            $normalized['display_rule'] = CampaignOrderBumpDisplayRule::from((string) ($attributes['display_rule'] ?? CampaignOrderBumpDisplayRule::Always->value));
        }

        if (! $partial || array_key_exists('display_rule_config', $attributes)) {
            $normalized['display_rule_config'] = $attributes['display_rule_config'] ?? null;
        }

        if (! $partial || array_key_exists('is_active', $attributes)) {
            $normalized['is_active'] = (bool) ($attributes['is_active'] ?? true);
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUpsell(CampaignUpsell $row): array
    {
        return [
            'uuid' => $row->uuid,
            'product_id' => $row->product_id,
            'product' => $row->product ? [
                'uuid' => $row->product->uuid,
                'sku' => $row->product->sku,
                'name' => $row->product->name,
            ] : null,
            'priority' => $row->priority,
            'sort_order' => $row->sort_order,
            'trigger' => $row->trigger->value,
            'trigger_config' => $row->trigger_config,
            'position' => $row->position->value,
            'discount_type' => $row->discount_type->value,
            'discount_value' => $row->discount_value,
            'is_active' => $row->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDownsell(CampaignDownsell $row): array
    {
        return [
            'uuid' => $row->uuid,
            'product_id' => $row->product_id,
            'product' => $row->product ? [
                'uuid' => $row->product->uuid,
                'sku' => $row->product->sku,
                'name' => $row->product->name,
            ] : null,
            'priority' => $row->priority,
            'sort_order' => $row->sort_order,
            'trigger' => $row->trigger->value,
            'trigger_config' => $row->trigger_config,
            'discount_type' => $row->discount_type->value,
            'discount_value' => $row->discount_value,
            'is_active' => $row->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrderBump(CampaignOrderBump $row): array
    {
        return [
            'uuid' => $row->uuid,
            'product_id' => $row->product_id,
            'product' => $row->product ? [
                'uuid' => $row->product->uuid,
                'sku' => $row->product->sku,
                'name' => $row->product->name,
            ] : null,
            'priority' => $row->priority,
            'sort_order' => $row->sort_order,
            'bump_price' => $row->bump_price,
            'position' => $row->position->value,
            'display_rule' => $row->display_rule->value,
            'display_rule_config' => $row->display_rule_config,
            'is_active' => $row->is_active,
        ];
    }
}
