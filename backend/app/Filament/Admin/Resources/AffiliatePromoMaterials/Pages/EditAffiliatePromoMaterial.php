<?php

namespace App\Filament\Admin\Resources\AffiliatePromoMaterials\Pages;

use App\Filament\Admin\Resources\AffiliatePromoMaterials\AffiliatePromoMaterialResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditAffiliatePromoMaterial extends EditRecord
{
    protected static string $resource = AffiliatePromoMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
