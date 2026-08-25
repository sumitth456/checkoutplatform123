<?php



namespace App\Filament\Admin\Resources\Users\Concerns;



use App\Models\User;

use App\Support\Admin\AdminActivityLogger;



trait LogsUserAdminActivity

{

    protected function logUserActivity(string $event, ?string $description = null, array $properties = []): void

    {

        if (! $this->record instanceof User) {

            return;

        }



        AdminActivityLogger::log(

            module: 'users',

            event: $event,

            description: $description,

            subject: $this->record,

            properties: $properties,

        );

    }

}

