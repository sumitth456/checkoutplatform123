<?php

namespace App\Filament\Admin\Resources\AffiliateTrackingLinks\Pages;

use App\Filament\Admin\Resources\AffiliateTrackingLinks\AffiliateTrackingLinkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAffiliateTrackingLinks extends ListRecords
{
    protected static string $resource = AffiliateTrackingLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
