<?php



namespace App\Filament\Admin\Resources\LandingPageAssets\Pages;



use App\Filament\Admin\Resources\LandingPageAssets\LandingPageAssetResource;

use App\Modules\LandingPage\Services\LandingPageAssetService;

use Filament\Resources\Pages\CreateRecord;

use InvalidArgumentException;



class CreateLandingPageAsset extends CreateRecord

{

    protected static string $resource = LandingPageAssetResource::class;



    protected function mutateFormDataBeforeCreate(array $data): array

    {

        $data['uploaded_by'] = auth()->id();



        try {

            return app(LandingPageAssetService::class)->finalizeFormData($data);

        } catch (InvalidArgumentException $exception) {

            $this->halt();

            throw $exception;

        }

    }

}

