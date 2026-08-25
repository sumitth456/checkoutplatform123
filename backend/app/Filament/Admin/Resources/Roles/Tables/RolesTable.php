<?php



namespace App\Filament\Admin\Resources\Roles\Tables;



use App\Support\Admin\AdminRoleGuard;

use Filament\Actions\DeleteAction;

use Filament\Actions\EditAction;

use Filament\Actions\ViewAction;

use Filament\Tables\Columns\TextColumn;

use Filament\Tables\Table;



class RolesTable

{

    public static function configure(Table $table): Table

    {

        return $table

            ->columns([

                TextColumn::make('name')

                    ->searchable()

                    ->sortable(),

                TextColumn::make('description')

                    ->limit(50)

                    ->placeholder('—'),

                TextColumn::make('permissions_count')

                    ->label('Permissions')

                    ->counts('permissions')

                    ->sortable(),

                TextColumn::make('users_count')

                    ->label('Users')

                    ->counts('users')

                    ->sortable(),

                TextColumn::make('updated_at')

                    ->dateTime()

                    ->sortable()

                    ->toggleable(isToggledHiddenByDefault: true),

            ])

            ->defaultSort('name')

            ->recordActions([

                ViewAction::make(),

                EditAction::make()
                    ->hidden(fn ($record): bool => AdminRoleGuard::isProtectedRole($record->name)),
                DeleteAction::make()
                    ->hidden(fn ($record): bool => AdminRoleGuard::isProtectedRole($record->name)),

            ]);

    }

}

