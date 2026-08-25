<?php

namespace App\Filament\Admin\Resources\Campaigns\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class CampaignInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::activityTimeline('Campaign timeline'),
                AdminInfolistComponents::detailsSection('Campaign details', [
                    Grid::make(3)->schema([
                        TextEntry::make('public_id')->label('Campaign ID')->copyable(),
                        TextEntry::make('uuid')->label('UUID')->copyable(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('name'),
                        TextEntry::make('brand.name')->label('Brand')->placeholder('—'),
                        TextEntry::make('subscriptionPlan.name')->label('Subscription plan')->placeholder('—'),
                        TextEntry::make('store_name')->label('Store')->placeholder('—'),
                        TextEntry::make('store_code')->label('Store code')->placeholder('—'),
                        TextEntry::make('currency'),
                        TextEntry::make('timezone'),
                        TextEntry::make('archived_at')->dateTime()->placeholder('—'),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                        TextEntry::make('description')->columnSpanFull()->placeholder('—'),
                        TextEntry::make('internal_notes')->columnSpanFull()->placeholder('—'),
                    ]),
                ]),
            ]);
    }
}
