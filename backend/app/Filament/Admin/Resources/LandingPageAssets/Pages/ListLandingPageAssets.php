<?php



namespace App\Filament\Admin\Resources\LandingPageAssets\Pages;



use App\Filament\Admin\Resources\LandingPageAssets\LandingPageAssetResource;

use App\Support\Admin\AdminAuthorization;

use Filament\Actions\CreateAction;

use Filament\Resources\Pages\ListRecords;



class ListLandingPageAssets extends ListRecords

{

    protected static string $resource = LandingPageAssetResource::class;



    protected function getHeaderActions(): array

    {

        return [

            CreateAction::make()

                ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'landing_pages.assets.manage')),

        ];

    }

}

