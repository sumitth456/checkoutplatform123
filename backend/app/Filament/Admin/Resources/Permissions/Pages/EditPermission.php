<?php



namespace App\Filament\Admin\Resources\Permissions\Pages;



use App\Filament\Admin\Resources\Permissions\PermissionResource;

use App\Support\Admin\AdminActivityLogger;

use Filament\Resources\Pages\EditRecord;



class EditPermission extends EditRecord

{

    protected static string $resource = PermissionResource::class;



    protected function afterSave(): void

    {

        AdminActivityLogger::log(

            module: 'permissions',

            event: 'updated',

            description: "Permission {$this->record->name} description updated",

            subject: $this->record,

        );

    }

}

