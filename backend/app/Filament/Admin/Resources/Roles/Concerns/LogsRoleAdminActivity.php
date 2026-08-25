<?php



namespace App\Filament\Admin\Resources\Roles\Concerns;



use App\Models\Role;

use App\Support\Admin\AdminActivityLogger;



trait LogsRoleAdminActivity

{

    protected function logRoleActivity(string $event, ?string $description = null, array $properties = []): void

    {

        if (! $this->record instanceof Role) {

            return;

        }



        AdminActivityLogger::log(

            module: 'roles',

            event: $event,

            description: $description,

            subject: $this->record,

            properties: $properties,

        );

    }

}

