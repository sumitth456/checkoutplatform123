<?php

namespace App\Filament\Affiliate\Resources\PortalPayouts\Pages;

use App\Filament\Affiliate\Resources\PortalPayouts\PortalPayoutResource;
use Filament\Resources\Pages\ListRecords;

class ListPortalPayouts extends ListRecords
{
    protected static string $resource = PortalPayoutResource::class;

    protected static ?string $title = 'Payouts';
}
