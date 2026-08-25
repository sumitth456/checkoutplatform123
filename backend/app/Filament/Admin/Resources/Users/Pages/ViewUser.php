<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\Concerns\LogsUserAdminActivity;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\User;
use App\Modules\Admin\Services\AdminUserService;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;

class ViewUser extends ViewRecord
{
    use LogsUserAdminActivity;

    protected static string $resource = UserResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->logUserActivity('viewed', "User {$this->record->email} viewed");
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('duplicate')
                ->label('Duplicate user')
                ->icon('heroicon-o-document-duplicate')
                ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'users.duplicate'))
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var User $copy */
                    $copy = app(AdminUserService::class)->duplicateUser(auth()->user(), $this->record);

                    $this->logUserActivity('duplicated', "User duplicated to {$copy->email}", ['copy_id' => $copy->id]);

                    Notification::make()
                        ->title('User duplicated')
                        ->body("Created inactive copy: {$copy->email}")
                        ->success()
                        ->send();

                    $this->redirect(UserResource::getUrl('edit', ['record' => $copy]));
                }),
            Action::make('send_password_reset')
                ->label('Send password reset email')
                ->icon('heroicon-o-envelope')
                ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'users.reset_password'))
                ->requiresConfirmation()
                ->action(function (): void {
                    Password::sendResetLink(['email' => $this->record->email]);

                    $this->logUserActivity('password_reset_email', "Password reset email sent to {$this->record->email}");

                    Notification::make()
                        ->title('Password reset email sent')
                        ->success()
                        ->send();
                }),
            Action::make('impersonate')
                ->label('Impersonate user')
                ->icon('heroicon-o-user-circle')
                ->color('danger')
                ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'users.impersonate') && auth()->id() !== $this->record->id)
                ->requiresConfirmation()
                ->action(function (): void {
                    session(['impersonator_id' => auth()->id()]);
                    Auth::login($this->record);

                    $this->redirect('/admin');
                }),
        ];
    }
}
