<?php

namespace App\Filament\Admin\Resources\Coupons\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use App\Models\Coupon;
use App\Modules\Order\Enums\CouponType;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class CouponInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::activityTimeline('Coupon timeline'),
                AdminInfolistComponents::detailsSection('Coupon details', [
                    Grid::make(3)->schema([
                        TextEntry::make('code')->copyable(),
                        TextEntry::make('name'),
                        TextEntry::make('type')->badge()->formatStateUsing(fn (string $state): string => str($state)->headline()),
                        TextEntry::make('value')
                            ->formatStateUsing(fn (Coupon $record): string => $record->type === CouponType::Percentage->value
                                ? number_format((float) $record->value, 2).'%'
                                : number_format((float) $record->value, 2).' '.($record->currency ?? 'USD')),
                        TextEntry::make('used_count')->label('Times used'),
                        TextEntry::make('minimum_order_amount')->money('USD')->placeholder('—'),
                        TextEntry::make('starts_at')->dateTime()->placeholder('—'),
                        TextEntry::make('expires_at')->dateTime()->placeholder('—'),
                        IconEntry::make('is_active')->boolean(),
                        TextEntry::make('description')->columnSpanFull()->placeholder('—'),
                    ]),
                ]),
            ]);
    }
}
