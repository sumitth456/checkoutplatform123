<?php

namespace App\Filament\Affiliate\Resources\PortalClicks\Pages;

use App\Filament\Affiliate\Resources\PortalClicks\PortalClickResource;
use Filament\Resources\Pages\ListRecords;

class ListPortalClicks extends ListRecords
{
    protected static string $resource = PortalClickResource::class;

    protected static ?string $title = 'Clicks';
}
