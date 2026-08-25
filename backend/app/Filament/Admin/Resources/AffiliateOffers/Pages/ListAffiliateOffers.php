<?php

namespace App\Filament\Admin\Resources\AffiliateOffers\Pages;

use App\Filament\Admin\Resources\AffiliateOffers\AffiliateOfferResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAffiliateOffers extends ListRecords
{
    protected static string $resource = AffiliateOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
