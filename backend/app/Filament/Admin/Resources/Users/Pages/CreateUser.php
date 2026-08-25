<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\Concerns\InteractsWithUserAdminForm;
use App\Filament\Admin\Resources\Users\Concerns\LogsUserAdminActivity;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\User;
use App\Modules\Admin\Services\AdminUserService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    use InteractsWithUserAdminForm;
    use LogsUserAdminActivity;

    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(AdminUserService::class)->createUser(auth()->user(), $data);
    }

    protected function afterCreate(): void
    {
        $this->logUserActivity('created', "User {$this->record->email} created");
    }
}
