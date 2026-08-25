<?php

namespace App\Filament\Admin\Resources\PaymentGateways\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use App\Models\PaymentGatewayConfig;
use App\Modules\Admin\Services\PaymentGatewayLoadService;
use App\Modules\Payment\Enums\GatewayWebhookStatus;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class PaymentGatewayInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::activityTimeline('Gateway activity'),
                AdminInfolistComponents::detailsSection('Gateway configuration', [
                    Grid::make(3)->schema([
                        TextEntry::make('name'),
                        TextEntry::make('slug'),
                        TextEntry::make('provider')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title()),
                        TextEntry::make('description')->columnSpanFull()->placeholder('—'),
                        TextEntry::make('sort_order')->label('Priority'),
                        TextEntry::make('weight'),
                        TextEntry::make('daily_limit_amount')->money('USD')->placeholder('No limit'),
                        TextEntry::make('monthly_limit_amount')->money('USD')->placeholder('No limit'),
                        IconEntry::make('failover_enabled')->boolean()->label('Failover enabled'),
                        TextEntry::make('load_daily_volume')
                            ->label('Daily volume')
                            ->state(function (PaymentGatewayConfig $record): string {
                                $stats = app(PaymentGatewayLoadService::class)->stats($record);

                                return number_format($stats['daily_volume'], 2).' / '.(
                                    $stats['daily_limit'] !== null ? number_format($stats['daily_limit'], 2) : '∞'
                                );
                            }),
                        TextEntry::make('load_success_rate')
                            ->label('Success rate today')
                            ->state(fn (PaymentGatewayConfig $record): string => app(PaymentGatewayLoadService::class)->stats($record)['success_rate_today'].'%'),
                        TextEntry::make('webhook_status')
                            ->badge()
                            ->formatStateUsing(fn (GatewayWebhookStatus|string $state): string => $state instanceof GatewayWebhookStatus ? $state->value : $state),
                        TextEntry::make('webhook_last_error')->placeholder('—')->columnSpanFull(),
                        IconEntry::make('is_active')->boolean()->label('Active'),
                        IconEntry::make('is_default')->boolean()->label('Default'),
                        IconEntry::make('test_mode')->boolean(),
                        IconEntry::make('supports_subscriptions')->boolean(),
                        IconEntry::make('supports_refunds')->boolean(),
                    ]),
                ]),
            ]);
    }
}
