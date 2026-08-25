<?php

namespace App\Filament\Admin\Resources\AffiliateTrackingLinks\Pages;

use App\Filament\Admin\Resources\AffiliateTrackingLinks\AffiliateTrackingLinkResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditAffiliateTrackingLink extends EditRecord
{
    protected static string $resource = AffiliateTrackingLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
