<?php



namespace App\Filament\Admin\Resources\CheckoutPaymentFailures\Tables;



use App\Filament\Admin\Exports\CheckoutPaymentFailureExporter;

use App\Support\Admin\AdminAuthorization;

use Filament\Actions\ExportBulkAction;

use Filament\Actions\ViewAction;

use Filament\Forms\Components\DatePicker;

use Filament\Tables\Columns\TextColumn;

use Filament\Tables\Filters\Filter;

use Filament\Tables\Filters\SelectFilter;

use Filament\Tables\Table;

use Illuminate\Database\Eloquent\Builder;



class CheckoutPaymentFailuresTable

{

    public static function configure(Table $table): Table

    {

        return $table

            ->columns([

                TextColumn::make('created_at')

                    ->dateTime()

                    ->sortable(),

                TextColumn::make('customer_email')

                    ->label('Customer')

                    ->searchable()

                    ->placeholder('—'),

                TextColumn::make('amount')

                    ->money(fn ($record) => $record->currency ?? 'USD')

                    ->sortable()

                    ->placeholder('—'),

                TextColumn::make('provider')

                    ->badge()

                    ->sortable(),

                TextColumn::make('failure_code')

                    ->badge()

                    ->color('danger')

                    ->searchable()

                    ->placeholder('—'),

                TextColumn::make('failure_message')

                    ->wrap()

                    ->limit(50)

                    ->searchable()

                    ->placeholder('—'),

                TextColumn::make('payment_intent_id')

                    ->label('Payment intent')

                    ->searchable()

                    ->toggleable(isToggledHiddenByDefault: true)

                    ->placeholder('—'),

                TextColumn::make('ip_address')

                    ->toggleable(isToggledHiddenByDefault: true)

                    ->placeholder('—'),

            ])

            ->defaultSort('created_at', 'desc')

            ->filters([

                SelectFilter::make('provider'),

                Filter::make('created_at')

                    ->schema([

                        DatePicker::make('from')->label('From'),

                        DatePicker::make('until')->label('Until'),

                    ])

                    ->query(function (Builder $query, array $data): Builder {

                        return $query

                            ->when(

                                $data['from'] ?? null,

                                fn (Builder $inner, $date): Builder => $inner->whereDate('created_at', '>=', $date),

                            )

                            ->when(

                                $data['until'] ?? null,

                                fn (Builder $inner, $date): Builder => $inner->whereDate('created_at', '<=', $date),

                            );

                    }),

            ])

            ->recordActions([

                ViewAction::make(),

            ])

            ->toolbarActions([

                ExportBulkAction::make()

                    ->exporter(CheckoutPaymentFailureExporter::class)

                    ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'logs.export')),

            ]);

    }

}

