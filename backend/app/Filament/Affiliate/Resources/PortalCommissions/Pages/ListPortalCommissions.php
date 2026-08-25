<?php

namespace App\Filament\Affiliate\Resources\PortalCommissions\Pages;

use App\Filament\Affiliate\Resources\PortalCommissions\PortalCommissionResource;
use Filament\Resources\Pages\ListRecords;

class ListPortalCommissions extends ListRecords
{
    protected static string $resource = PortalCommissionResource::class;

    protected static ?string $title = 'Commissions';
}
