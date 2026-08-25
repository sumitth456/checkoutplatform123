<?php

namespace App\Filament\Admin\Resources\AffiliateOffers\Pages;

use App\Filament\Admin\Resources\AffiliateOffers\AffiliateOfferResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAffiliateOffer extends ViewRecord
{
    protected static string $resource = AffiliateOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
