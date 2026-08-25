<?php

namespace App\Filament\Admin\Resources\LandingPages\Pages;

use App\Filament\Admin\Pages\LandingPageManagement\PublishedLandingPages;
use App\Filament\Admin\Resources\LandingPages\LandingPageResource;
use Filament\Resources\Pages\ListRecords;

class ListLandingPages extends ListRecords
{
    protected static string $resource = LandingPageResource::class;

    public function mount(): void
    {
        $this->redirect(PublishedLandingPages::getUrl(), navigate: false);
    }
}
