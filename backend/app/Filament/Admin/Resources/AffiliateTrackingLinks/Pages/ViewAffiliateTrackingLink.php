<?php

namespace App\Filament\Admin\Resources\AffiliateTrackingLinks\Pages;

use App\Filament\Admin\Resources\AffiliateTrackingLinks\AffiliateTrackingLinkResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAffiliateTrackingLink extends ViewRecord
{
    protected static string $resource = AffiliateTrackingLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
