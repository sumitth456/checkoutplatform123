<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\Concerns\InteractsWithUserAdminForm;
use App\Filament\Admin\Resources\Users\Concerns\LogsUserAdminActivity;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\User;
use App\Modules\Admin\Services\AdminUserService;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class EditUser extends EditRecord
{
    use InteractsWithUserAdminForm;
    use LogsUserAdminActivity;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset_password')
                ->label('Reset password')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'users.reset_password'))
                ->form([
                    TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->required()
                        ->rule(PasswordRule::min(8)->letters()->mixedCase()->numbers()),
                    TextInput::make('password_confirmation')
                        ->password()
                        ->revealable()
                        ->same('password')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    app(AdminUserService::class)->resetPassword($this->record, $data['password']);

                    $this->logUserActivity('password_reset', "Password reset for {$this->record->email}");

                    Notification::make()
                        ->title('Password updated')
                        ->success()
                        ->send();
                }),
            ...parent::getHeaderActions(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        return app(AdminUserService::class)->updateUser(auth()->user(), $record, $data);
    }

    protected function afterSave(): void
    {
        $this->logUserActivity('updated', "User {$this->record->email} updated");
    }
}
