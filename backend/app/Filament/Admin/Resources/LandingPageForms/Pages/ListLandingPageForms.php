<?php

namespace App\Filament\Admin\Resources\LandingPageForms\Pages;

use App\Filament\Admin\Resources\LandingPageForms\LandingPageFormResource;
use Filament\Resources\Pages\ListRecords;

class ListLandingPageForms extends ListRecords
{
    protected static string $resource = LandingPageFormResource::class;
}