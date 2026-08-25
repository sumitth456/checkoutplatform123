<?php

namespace App\Filament\Admin\Resources\AffiliatePayouts\Pages;

use App\Filament\Admin\Resources\AffiliatePayouts\AffiliatePayoutResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAffiliatePayout extends ViewRecord
{
    protected static string $resource = AffiliatePayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
