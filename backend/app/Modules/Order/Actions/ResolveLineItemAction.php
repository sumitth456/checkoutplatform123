<?php



namespace App\Modules\Order\Actions;



use App\Models\Campaign;

use App\Models\CampaignOrderBump;

use App\Models\CampaignProduct;

use App\Models\Variant;

use App\Modules\Campaign\Services\CampaignCheckoutPricingService;

use App\Modules\Campaign\Services\CampaignVariantMatrixService;

use App\Modules\Order\DTOs\FunnelLineItemDTO;

use App\Modules\Order\DTOs\ResolvedLineItemDTO;

use Illuminate\Validation\ValidationException;



class ResolveLineItemAction

{

    public function __construct(

        private readonly CampaignCheckoutPricingService $pricing,

        private readonly CampaignVariantMatrixService $variantMatrix,

    ) {}



    public function execute(FunnelLineItemDTO $lineItem, int $index, ?Campaign $campaign = null): ResolvedLineItemDTO

    {

        $variant = $this->resolveVariant($lineItem, $campaign);



        if ($variant === null) {

            throw ValidationException::withMessages([

                "items.{$index}.variant_uuid" => ['Unable to resolve catalog line item.'],

            ]);

        }



        $variant->loadMissing('product');



        $line = $this->resolveCampaignLine($campaign, $lineItem, $variant);

        $orderBump = $this->resolveOrderBump($campaign, $lineItem);



        $quantity = max(1, $lineItem->quantity);

        $unitPrice = $this->pricing->resolveUnitPrice($variant, $campaign, $line, $orderBump);

        $currency = strtoupper((string) ($variant->currency ?? $variant->product->currency ?? 'USD'));



        return new ResolvedLineItemDTO(

            variant: $variant,

            quantity: $quantity,

            unitPrice: $unitPrice,

            lineTotal: round($unitPrice * $quantity, 2),

            currency: $currency,

            name: (string) ($variant->name ?? $variant->product->name),

            sku: (string) $variant->sku,

        );

    }



    private function resolveVariant(FunnelLineItemDTO $lineItem, ?Campaign $campaign): ?Variant

    {

        if ($lineItem->variantUuid !== null) {

            return Variant::query()->where('uuid', $lineItem->variantUuid)->first();

        }



        if ($lineItem->variantSku !== null) {

            return Variant::query()->where('sku', $lineItem->variantSku)->first();

        }



        if ($campaign !== null && ($lineItem->color !== null || $lineItem->size !== null)) {

            $line = $this->findLineForItem($campaign, $lineItem);



            if ($line !== null) {

                $uuid = $this->variantMatrix->findVariantUuid($line, $lineItem->color, $lineItem->size);



                if ($uuid !== null) {

                    return Variant::query()->where('uuid', $uuid)->first();

                }

            }

        }



        if ($lineItem->productUuid !== null) {

            $query = Variant::query()->whereHas('product', fn ($q) => $q->where('uuid', $lineItem->productUuid));



            if ($lineItem->size !== null) {

                $query->where(function ($q) use ($lineItem): void {

                    $q->where('name', $lineItem->size)

                        ->orWhereJsonContains('options->size', $lineItem->size);

                });

            }



            if ($lineItem->color !== null) {

                $query->where(function ($q) use ($lineItem): void {

                    $q->whereJsonContains('options->color', $lineItem->color)

                        ->orWhereJsonContains('options->Color', $lineItem->color);

                });

            }



            return $query->first();

        }



        if ($lineItem->productSku !== null) {

            return Variant::query()

                ->whereHas('product', fn ($q) => $q->where('sku', $lineItem->productSku))

                ->when($lineItem->size !== null, fn ($q) => $q->where('name', $lineItem->size))

                ->first();

        }



        return null;

    }



    private function resolveCampaignLine(?Campaign $campaign, FunnelLineItemDTO $lineItem, Variant $variant): ?CampaignProduct

    {

        if ($campaign === null) {

            return null;

        }



        if ($lineItem->campaignProductLineId !== null) {

            return $campaign->campaignProducts()->where('id', $lineItem->campaignProductLineId)->first();

        }



        return $this->pricing->findCampaignProductLine($campaign, $variant);

    }



    private function findLineForItem(Campaign $campaign, FunnelLineItemDTO $lineItem): ?CampaignProduct

    {

        if ($lineItem->campaignProductLineId !== null) {

            return $campaign->campaignProducts()->where('id', $lineItem->campaignProductLineId)->first();

        }



        if ($lineItem->productUuid !== null) {

            return $campaign->campaignProducts()

                ->where('is_active', true)

                ->whereHas('product', fn ($q) => $q->where('uuid', $lineItem->productUuid))

                ->first();

        }



        return null;

    }



    private function resolveOrderBump(?Campaign $campaign, FunnelLineItemDTO $lineItem): ?CampaignOrderBump

    {

        if ($campaign === null || blank($lineItem->orderBumpUuid)) {

            return null;

        }



        return $campaign->orderBumps()

            ->where('uuid', $lineItem->orderBumpUuid)

            ->where('is_active', true)

            ->first();

    }

}

