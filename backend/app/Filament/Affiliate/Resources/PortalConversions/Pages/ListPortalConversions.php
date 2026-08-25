<?php

namespace App\Filament\Affiliate\Resources\PortalConversions\Pages;

use App\Filament\Affiliate\Resources\PortalConversions\PortalConversionResource;
use Filament\Resources\Pages\ListRecords;

class ListPortalConversions extends ListRecords
{
    protected static string $resource = PortalConversionResource::class;

    protected static ?string $title = 'Conversions';
}
