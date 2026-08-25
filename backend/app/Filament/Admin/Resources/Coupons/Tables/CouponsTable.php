<?php

namespace App\Filament\Admin\Resources\Coupons\Tables;

use App\Filament\Admin\Exports\CouponExporter;
use App\Models\Coupon;
use App\Modules\Order\Enums\CouponType;
use App\Support\Admin\AdminActivityLogger;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CouponsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('orders'))
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->headline())
                    ->sortable(),
                TextColumn::make('value')
                    ->sortable()
                    ->formatStateUsing(fn (Coupon $record): string => $record->type === CouponType::Percentage->value
                        ? number_format((float) $record->value, 0).'%'
                        : number_format((float) $record->value, 2)),
                TextColumn::make('used_count')
                    ->label('Used')
                    ->sortable(),
                TextColumn::make('max_uses')
                    ->label('Max uses')
                    ->placeholder('∞')
                    ->toggleable(),
                TextColumn::make('orders_count')
                    ->label('Orders')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('schedule_status')
                    ->label('Status')
                    ->badge()
                    ->state(function (Coupon $record): string {
                        if (! $record->is_active) {
                            return 'inactive';
                        }
                        if ($record->isScheduled()) {
                            return 'scheduled';
                        }
                        if ($record->isExpired()) {
                            return 'expired';
                        }

                        return 'active';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'scheduled' => 'warning',
                        'expired' => 'danger',
                        default => 'gray',
                    }),
                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->disabled(fn (): bool => ! AdminAuthorization::can(auth()->user(), 'coupons.update'))
                    ->afterStateUpdated(function (Coupon $record, bool $state): void {
                        AdminActivityLogger::log(
                            module: 'coupons',
                            event: $state ? 'enabled' : 'disabled',
                            description: $state
                                ? "Coupon {$record->code} enabled"
                                : "Coupon {$record->code} disabled",
                            subject: $record,
                        );
                    }),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('code')
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('type')
                    ->options(collect(CouponType::cases())->mapWithKeys(
                        fn (CouponType $type): array => [$type->value => str($type->name)->headline()]
                    )->all()),
                SelectFilter::make('is_active')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(CouponExporter::class)
                        ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'coupons.export')),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
