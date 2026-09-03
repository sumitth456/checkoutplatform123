<?php

namespace App\Filament\Admin\Resources\LandingPages\Tables;

use App\Models\LandingPage;
use App\Modules\LandingPage\Enums\LandingPageStatus;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LandingPagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->searchable()->copyable(),
                TextColumn::make('publicUrl')
                    ->label('Public URL')
                    ->state(fn (LandingPage $record): string => $record->publicUrl())
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('product.name')
                    ->label('Product')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('variant.sku')
                    ->label('SKU')
                    ->state(fn (LandingPage $record): string => (string) ($record->variant?->sku ?? $record->product?->sku ?? '—'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('campaign.name')->label('Campaign')->sortable(),
                TextColumn::make('view_count')->label('Views')->sortable(),
                TextColumn::make('conversion_count')->label('Conversions')->sortable(),
                TextColumn::make('revenue_total')->money('USD')->label('Revenue'),
                TextColumn::make('published_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(LandingPageStatus::cases())->mapWithKeys(
                        fn (LandingPageStatus $s): array => [$s->value => $s->label()]
                    )->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('builder')
                    ->label('Builder')
                    ->icon('heroicon-o-code-bracket-square')
                    ->url(fn (LandingPage $record): string => \App\Filament\Admin\Resources\LandingPages\LandingPageResource::getUrl('builder', ['record' => $record])),
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->url(fn (LandingPage $record): string => route('landing-pages.preview', $record))
                    ->openUrlInNewTab(),
                Action::make('public_url')
                    ->label('Open live')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->visible(fn (LandingPage $record): bool => $record->status === LandingPageStatus::Published)
                    ->url(fn (LandingPage $record): string => $record->publicUrl())
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
