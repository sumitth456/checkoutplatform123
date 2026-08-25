<?php

namespace App\Filament\Admin\Resources\Funnels\Tables;

use App\Filament\Admin\Exports\FunnelExporter;
use App\Models\Funnel;
use App\Modules\Admin\Services\FunnelAdminService;
use App\Modules\Order\Enums\FunnelStatus;
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
use Illuminate\Database\Eloquent\Builder;

class FunnelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['orders', 'steps']))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (FunnelStatus|string $state): string => $state instanceof FunnelStatus ? $state->value : $state)
                    ->sortable(),
                TextColumn::make('steps_count')
                    ->label('Steps')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('orders_count')
                    ->label('Orders')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('revenue_total')
                    ->label('Revenue')
                    ->state(function (Funnel $record): string {
                        $performance = app(FunnelAdminService::class)->performance($record);

                        return number_format($performance['revenue'], 2).' '.$record->currency;
                    })
                    ->toggleable(),
                TextColumn::make('entryProduct.name')
                    ->label('Entry product')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->options(collect(FunnelStatus::cases())->mapWithKeys(
                        fn (FunnelStatus $status): array => [$status->value => str($status->name)->headline()]
                    )->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(FunnelExporter::class)
                        ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'funnels.export')),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
