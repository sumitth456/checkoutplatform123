<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Resources\PaymentGateways\PaymentGatewayResource;
use App\Filament\Admin\Resources\StripeWebhookEvents\StripeWebhookEventResource;
use App\Models\PaymentGatewayConfig;
use App\Modules\Admin\Services\AdminWebhookService;
use App\Modules\Payment\Enums\GatewayWebhookStatus;
use App\Support\Admin\AdminActivityLogger;
use App\Support\Admin\AdminAuthorization;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Resources\Concerns\HasTabs;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use UnitEnum;

class WebhookIntegrations extends Page implements Tables\Contracts\HasTable
{
    use HasTabs;
    use Tables\Concerns\InteractsWithTable {
        makeTable as makeBaseTable;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ADMIN;

    protected static ?int $navigationSort = 63;

    protected static ?string $navigationLabel = 'Webhooks';

    protected static ?string $title = 'Webhooks';

    protected static ?string $slug = 'webhooks';

    #[Url(as: 'tab')]
    public ?string $activeTab = null;

    public static function canAccess(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'webhooks.view');
    }

    public function mount(): void
    {
        $this->loadDefaultActiveTab();

        AdminActivityLogger::log(
            module: 'webhooks',
            event: 'viewed',
            description: 'Webhooks integrations page viewed',
        );
    }

    public function getTitle(): string|Htmlable
    {
        return 'Inbound webhooks';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('eventLog')
                ->label('Event log')
                ->icon('heroicon-o-signal')
                ->url(StripeWebhookEventResource::getUrl('index'))
                ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'logs.view')),
            Action::make('gateways')
                ->label('Payment gateways')
                ->icon('heroicon-o-credit-card')
                ->url(PaymentGatewayResource::getUrl('index'))
                ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'payment_gateways.view')),
            Action::make('verifyAll')
                ->label('Verify all active')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'webhooks.manage'))
                ->action(function (): void {
                    $result = app(AdminWebhookService::class)->verifyAllActiveGateways();

                    Notification::make()
                        ->title('Webhook verification complete')
                        ->body("{$result['verified']} verified, {$result['failed']} failed.")
                        ->success()
                        ->send();
                }),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                SchemaView::make('filament.admin.pages.webhooks-overview')
                    ->viewData(fn (): array => [
                        'overview' => app(AdminWebhookService::class)->overview(),
                    ]),
                $this->getTabsContentComponent(),
                EmbeddedTable::make(),
            ]);
    }

    public function getTabs(): array
    {
        $counts = app(AdminWebhookService::class)->tabCounts();
        $badge = fn (string $key): string => (string) ($counts[$key] ?? 0);

        return [
            'all' => Tab::make('All')
                ->badge($badge('all')),
            'verified' => Tab::make('Verified')
                ->badge($badge('verified'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('webhook_status', GatewayWebhookStatus::Verified)),
            'failed' => Tab::make('Failed')
                ->badge($badge('failed'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('webhook_status', GatewayWebhookStatus::Failed)),
            'unknown' => Tab::make('Unknown')
                ->badge($badge('unknown'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('webhook_status', GatewayWebhookStatus::Unknown)),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return PaymentGatewayConfig::query()
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function table(Table $table): Table
    {
        $webhooks = app(AdminWebhookService::class);

        return $table
            ->heading('Gateway webhook status')
            ->description('Verify credentials include webhook secrets/IDs, then run a configuration check per gateway.')
            ->query(fn (): Builder => $this->getTableQuery())
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (PaymentGatewayConfig $record): string => $record->slug),
                TextColumn::make('provider')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title()),
                TextColumn::make('endpoint')
                    ->label('Endpoint')
                    ->state(fn (PaymentGatewayConfig $record): string => $webhooks->endpointForGateway($record) ?? '—')
                    ->copyable()
                    ->copyMessage('Endpoint copied')
                    ->wrap(),
                TextColumn::make('webhook_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (GatewayWebhookStatus $state): string => match ($state) {
                        GatewayWebhookStatus::Verified => 'success',
                        GatewayWebhookStatus::Failed => 'danger',
                        GatewayWebhookStatus::Pending => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('webhook_verified_at')
                    ->label('Verified at')
                    ->dateTime()
                    ->placeholder('—'),
                TextColumn::make('webhook_last_error')
                    ->label('Last error')
                    ->wrap()
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->recordActions([
                Action::make('verify')
                    ->label('Verify')
                    ->icon('heroicon-o-signal')
                    ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'webhooks.manage'))
                    ->action(function (PaymentGatewayConfig $record): void {
                        $gateway = app(AdminWebhookService::class)->verifyGateway($record);

                        if ($gateway->webhook_status === GatewayWebhookStatus::Verified) {
                            Notification::make()
                                ->title('Webhook verified')
                                ->success()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Webhook check failed')
                            ->body($gateway->webhook_last_error ?? 'Unknown error')
                            ->danger()
                            ->send();
                    }),
                Action::make('reset')
                    ->label('Reset status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'webhooks.manage'))
                    ->action(function (PaymentGatewayConfig $record): void {
                        app(AdminWebhookService::class)->resetGatewayStatus($record);

                        Notification::make()
                            ->title('Webhook status reset')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordUrl(fn (PaymentGatewayConfig $record): string => PaymentGatewayResource::getUrl('view', ['record' => $record]));
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->query(fn (): Builder => $this->getTableQuery())
            ->modifyQueryUsing($this->modifyQueryWithActiveTab(...));
    }
}
