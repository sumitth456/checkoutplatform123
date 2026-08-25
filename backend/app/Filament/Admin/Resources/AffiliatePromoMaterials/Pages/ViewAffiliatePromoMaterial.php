<?php

namespace App\Filament\Admin\Resources\AffiliatePromoMaterials\Pages;

use App\Filament\Admin\Resources\AffiliatePromoMaterials\AffiliatePromoMaterialResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAffiliatePromoMaterial extends ViewRecord
{
    protected static string $resource = AffiliatePromoMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
