<?php



namespace App\Filament\Admin\Resources\LandingPageAssets\Pages;



use App\Filament\Admin\Resources\LandingPageAssets\LandingPageAssetResource;

use App\Modules\LandingPage\Services\LandingPageAssetService;

use Filament\Resources\Pages\EditRecord;

use InvalidArgumentException;



class EditLandingPageAsset extends EditRecord

{

    protected static string $resource = LandingPageAssetResource::class;



    protected function mutateFormDataBeforeSave(array $data): array

    {

        try {

            return app(LandingPageAssetService::class)->finalizeFormData($data, $this->record);

        } catch (InvalidArgumentException $exception) {

            $this->halt();

            throw $exception;

        }

    }

}

