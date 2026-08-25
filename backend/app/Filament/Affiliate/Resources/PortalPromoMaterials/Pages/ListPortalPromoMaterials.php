<?php

namespace App\Filament\Affiliate\Resources\PortalPromoMaterials\Pages;

use App\Filament\Affiliate\Resources\PortalPromoMaterials\PortalPromoMaterialResource;
use Filament\Resources\Pages\ListRecords;

class ListPortalPromoMaterials extends ListRecords
{
    protected static string $resource = PortalPromoMaterialResource::class;

    protected static ?string $title = 'Promo Materials';
}
