<?php

namespace App\Filament\Admin\Resources\AffiliatePromoMaterials\Pages;

use App\Filament\Admin\Resources\AffiliatePromoMaterials\AffiliatePromoMaterialResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAffiliatePromoMaterials extends ListRecords
{
    protected static string $resource = AffiliatePromoMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
