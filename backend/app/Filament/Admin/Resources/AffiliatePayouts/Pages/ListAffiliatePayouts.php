<?php

namespace App\Filament\Admin\Resources\AffiliatePayouts\Pages;

use App\Filament\Admin\Resources\AffiliatePayouts\AffiliatePayoutResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAffiliatePayouts extends ListRecords
{
    protected static string $resource = AffiliatePayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
