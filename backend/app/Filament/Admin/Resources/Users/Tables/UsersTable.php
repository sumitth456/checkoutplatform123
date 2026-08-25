<?php



namespace App\Filament\Admin\Resources\Users\Tables;



use App\Filament\Admin\Exports\UserExporter;

use App\Support\Admin\AdminAuthorization;

use Filament\Actions\BulkActionGroup;

use Filament\Actions\DeleteBulkAction;

use Filament\Actions\EditAction;

use Filament\Actions\ExportBulkAction;

use Filament\Actions\ForceDeleteBulkAction;

use Filament\Actions\RestoreBulkAction;

use Filament\Actions\ViewAction;

use Filament\Tables\Columns\TextColumn;

use Filament\Tables\Filters\SelectFilter;

use Filament\Tables\Filters\TrashedFilter;

use Filament\Tables\Table;



class UsersTable

{

    public static function configure(Table $table): Table

    {

        return $table

            ->columns([

                TextColumn::make('name')

                    ->searchable(['name', 'first_name', 'last_name', 'email'])

                    ->sortable(),

                TextColumn::make('email')

                    ->searchable()

                    ->copyable(),

                TextColumn::make('roles.name')

                    ->label('Roles')

                    ->badge()

                    ->placeholder('—'),

                TextColumn::make('status')

                    ->badge()

                    ->color(fn (string $state): string => match ($state) {

                        'active' => 'success',

                        'suspended' => 'danger',

                        default => 'gray',

                    })

                    ->sortable(),

                TextColumn::make('last_login_at')

                    ->dateTime()

                    ->placeholder('Never')

                    ->sortable(),

                TextColumn::make('created_at')

                    ->dateTime()

                    ->sortable()

                    ->toggleable(isToggledHiddenByDefault: true),

            ])

            ->defaultSort('name')

            ->filters([

                TrashedFilter::make(),

                SelectFilter::make('status')

                    ->options([

                        'active' => 'Active',

                        'inactive' => 'Inactive',

                        'suspended' => 'Suspended',

                    ]),

            ])

            ->recordActions([

                ViewAction::make(),

                EditAction::make(),

            ])

            ->toolbarActions([

                BulkActionGroup::make([

                    ExportBulkAction::make()

                        ->exporter(UserExporter::class)

                        ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'users.export')),

                    DeleteBulkAction::make(),

                    ForceDeleteBulkAction::make(),

                    RestoreBulkAction::make(),

                ]),

            ]);

    }

}

