<?php

namespace App\Filament\Affiliate\Resources\PortalTrackingLinks\Pages;

use App\Filament\Affiliate\Resources\PortalTrackingLinks\PortalTrackingLinkResource;
use Filament\Resources\Pages\ListRecords;

class ListPortalTrackingLinks extends ListRecords
{
    protected static string $resource = PortalTrackingLinkResource::class;

    protected static ?string $title = 'Tracking Links';
}
