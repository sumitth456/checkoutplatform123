<?php

namespace App\Filament\Admin\Resources\AffiliatePayouts\Pages;

use App\Filament\Admin\Resources\AffiliatePayouts\AffiliatePayoutResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAffiliatePayout extends EditRecord
{
    protected static string $resource = AffiliatePayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
