<?php

namespace App\Filament\Admin\Resources\AffiliatePayouts\Tables;

use App\Models\AffiliatePayout;
use App\Modules\Affiliate\Enums\PayoutStatus;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class AffiliatePayoutsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('affiliate.name')
                    ->label('Affiliate')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (PayoutStatus|string $state): string => $state instanceof PayoutStatus ? str($state->name)->headline() : $state)
                    ->sortable(),
                TextColumn::make('amount')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('reference')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('approved_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('paid_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(PayoutStatus::cases())->mapWithKeys(
                        fn (PayoutStatus $status): array => [$status->value => str($status->name)->headline()]
                    )->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->label('Approve selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'affiliate_payouts.manage'))
                        ->action(function (Collection $records): void {
                            $count = 0;

                            $records->each(function (AffiliatePayout $payout) use (&$count): void {
                                if ($payout->status !== PayoutStatus::Pending) {
                                    return;
                                }

                                $payout->update([
                                    'status' => PayoutStatus::Approved,
                                    'approved_by' => auth()->id(),
                                    'approved_at' => now(),
                                ]);

                                $count++;
                            });

                            Notification::make()
                                ->title("{$count} payout(s) approved")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('markPaid')
                        ->label('Mark as paid')
                        ->icon('heroicon-o-banknotes')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'affiliate_payouts.manage'))
                        ->action(function (Collection $records): void {
                            $count = 0;

                            $records->each(function (AffiliatePayout $payout) use (&$count): void {
                                if (! in_array($payout->status, [PayoutStatus::Pending, PayoutStatus::Approved], true)) {
                                    return;
                                }

                                $payout->update([
                                    'status' => PayoutStatus::Paid,
                                    'approved_by' => $payout->approved_by ?? auth()->id(),
                                    'approved_at' => $payout->approved_at ?? now(),
                                    'paid_at' => now(),
                                ]);

                                $count++;
                            });

                            Notification::make()
                                ->title("{$count} payout(s) marked as paid")
                                ->success()
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
