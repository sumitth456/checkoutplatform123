<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use App\Models\Campaign;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Admin\AdminRoleGuard;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Basic Information')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('first_name')
                                ->label('First name')
                                ->required()
                                ->maxLength(120),
                            TextInput::make('last_name')
                                ->label('Last name')
                                ->required()
                                ->maxLength(120),
                            TextInput::make('username')
                                ->required()
                                ->maxLength(120)
                                ->unique(ignoreRecord: true)
                                ->alphaDash()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn ($set, ?string $state) => $set('username', Str::lower((string) $state))),
                            TextInput::make('email')
                                ->email()
                                ->required()
                                ->unique(ignoreRecord: true),
                            TextInput::make('phone')
                                ->tel()
                                ->maxLength(40),
                            Select::make('status')
                                ->options([
                                    'active' => 'Active',
                                    'inactive' => 'Inactive',
                                ])
                                ->default('active')
                                ->required()
                                ->native(false),
                        ]),
                        Grid::make(2)->schema([
                            TextInput::make('password')
                                ->password()
                                ->revealable()
                                ->rule(Password::min(8)->letters()->mixedCase()->numbers())
                                ->same('password_confirmation')
                                ->required(fn (string $operation): bool => $operation === 'create')
                                ->visibleOn(['create'])
                                ->dehydrated(fn (?string $state): bool => filled($state)),
                            TextInput::make('password_confirmation')
                                ->label('Confirm password')
                                ->password()
                                ->revealable()
                                ->required(fn (string $operation): bool => $operation === 'create')
                                ->visibleOn(['create']),
                        ]),
                    ]),
                Section::make('Role & Permissions')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('role_name')
                            ->label('Role')
                            ->options(fn (): array => Role::query()
                                ->whereIn('name', AdminRoleGuard::assignableRoles())
                                ->orderBy('name')
                                ->pluck('name', 'name')
                                ->all())
                            ->searchable()
                            ->required()
                            ->native(false)
                            ->helperText('Permissions are inherited from the selected role.'),
                        Section::make('Custom Permission Overrides')
                            ->description('Optional extra permissions added on top of the role. These never remove inherited role permissions.')
                            ->collapsed()
                            ->schema([
                                CheckboxList::make('extra_permission_ids')
                                    ->label('Additional permissions')
                                    ->options(fn (): array => Permission::query()
                                        ->orderBy('group')
                                        ->orderBy('name')
                                        ->get()
                                        ->mapWithKeys(fn (Permission $permission): array => [
                                            $permission->id => "{$permission->group} · {$permission->name}",
                                        ])
                                        ->all())
                                    ->columns(2)
                                    ->searchable()
                                    ->bulkToggleable(),
                            ]),
                    ]),
                Section::make('Campaign Access')
                    ->columnSpanFull()
                    ->schema([
                        CheckboxList::make('campaign_ids')
                            ->label('Campaigns')
                            ->options(fn (): array => Campaign::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->columns(2)
                            ->searchable()
                            ->bulkToggleable()
                            ->helperText('Use “Select all” in the list header to grant every campaign.'),
                    ]),
                Section::make('Security')
                    ->schema([
                        Textarea::make('ip_whitelist_text')
                            ->label('IP whitelist')
                            ->rows(4)
                            ->placeholder("192.168.1.1\n103.45.66.2")
                            ->helperText('One IP address per line. Leave empty to allow any IP.')
                            ->columnSpanFull(),
                        Checkbox::make('two_factor_enabled')
                            ->label('Enable two-factor authentication'),
                        Checkbox::make('require_password_change')
                            ->label('Require password change on first login'),
                        Checkbox::make('force_logout_other_devices')
                            ->label('Force logout from other devices'),
                    ]),
                Section::make('Audit Information')
                    ->visibleOn(['edit'])
                    ->schema([
                        TextInput::make('createdBy.name')
                            ->label('Created by')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('updatedBy.name')
                            ->label('Updated by')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('last_login_at')
                            ->label('Last login at')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('last_login_ip')
                            ->label('Last login IP')
                            ->disabled()
                            ->dehydrated(false),
                    ]),
            ]);
    }
}
