<?php

namespace App\Filament\Admin\Resources\AchProviders\Schemas;

use App\Modules\Ach\Enums\AchProviderStatus;
use App\Modules\Ach\Enums\AchProviderType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class AchProviderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('ACH Provider')->columnSpanFull()->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')->required()->live(onBlur: true)
                        ->afterStateUpdated(fn ($set, $state, $record) => $record === null ? $set('slug', Str::slug($state ?? '')) : null),
                    TextInput::make('slug')->required()->unique(ignoreRecord: true),
                    Select::make('provider')->label('Driver')->options(collect(AchProviderType::cases())->mapWithKeys(
                        fn (AchProviderType $type): array => [$type->value => $type->label()],
                    )->all())->required()->native(false),
                    Select::make('status')->options(collect(AchProviderStatus::cases())->mapWithKeys(
                        fn (AchProviderStatus $status): array => [$status->value => str($status->name)->headline()],
                    )->all())->required()->native(false),
                    Toggle::make('sandbox_mode')->label('Sandbox mode'),
                    Toggle::make('is_default')->label('Default provider'),
                    TextInput::make('priority')->numeric()->default(100),
                ]),
            ]),
            Section::make('API credentials')->columnSpanFull()->schema([
                Grid::make(2)->schema([
                    TextInput::make('api_key')->password()->revealable()
                        ->dehydrated(fn (?string $state): bool => filled($state)),
                    TextInput::make('secret_key')->password()->revealable()
                        ->dehydrated(fn (?string $state): bool => filled($state)),
                    TextInput::make('merchant_id'),
                ]),
            ]),
        ]);
    }
}
