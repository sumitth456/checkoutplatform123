<?php

namespace App\Filament\Admin\Resources\CallCenters\Schemas;

use App\Models\Campaign;
use App\Models\Role;
use App\Models\User;
use App\Modules\CallCenter\Enums\CallCenterStatus;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CallCenterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('General')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            Select::make('status')
                                ->options(collect(CallCenterStatus::cases())->mapWithKeys(
                                    fn (CallCenterStatus $status): array => [$status->value => str($status->name)->headline()]
                                )->all())
                                ->required()
                                ->default(CallCenterStatus::Active->value)
                                ->native(false),
                            TextInput::make('timezone')
                                ->default('UTC')
                                ->required()
                                ->helperText('IANA timezone, e.g. America/New_York'),
                            TextInput::make('default_language')
                                ->label('Default language')
                                ->default('en')
                                ->maxLength(10),
                        ]),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Contact')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('phone_number')
                                ->tel()
                                ->maxLength(40),
                            TextInput::make('extension')
                                ->maxLength(20),
                            TextInput::make('country_code')
                                ->label('Country code')
                                ->maxLength(5)
                                ->placeholder('+1'),
                        ]),
                    ]),
                Section::make('Access & assignments')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('allowed_campaign_ids')
                            ->label('Allowed campaigns')
                            ->multiple()
                            ->options(fn (): array => Campaign::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload(),
                        Select::make('assigned_user_ids')
                            ->label('Assigned users')
                            ->multiple()
                            ->options(fn (): array => User::query()
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (User $user): array => [
                                    $user->id => trim($user->name ?: "{$user->first_name} {$user->last_name}") ?: $user->email,
                                ])
                                ->all())
                            ->searchable()
                            ->preload(),
                        Select::make('assigned_role_ids')
                            ->label('Assigned roles')
                            ->multiple()
                            ->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload(),
                        KeyValue::make('permissions')
                            ->keyLabel('Permission')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
