<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Resources\PaymentGateways\PaymentGatewayResource;
use App\Models\PaymentGatewayConfig;
use App\Modules\Admin\Services\GatewayRoutingAdminService;
use App\Modules\Admin\Services\PaymentGatewayLoadService;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Support\Admin\AdminActivityLogger;
use App\Support\Admin\AdminAuthorization;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
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

class GatewayRouting extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable {
        makeTable as makeBaseTable;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsUpDown;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::GATEWAYS;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Gateway Routing';

    protected static ?string $title = 'Gateway Routing';

    protected static ?string $slug = 'gateway-routing';

    #[Url(as: 'reordering')]
    public bool $isTableReordering = false;

    public static function canAccess(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'gateway_routing.view');
    }

    public function mount(): void
    {
        AdminActivityLogger::log(
            module: 'gateway_routing',
            event: 'viewed',
            description: 'Gateway routing page viewed',
        );
    }

    public function getTitle(): string|Htmlable
    {
        return 'Gateway Routing';
    }

    protected function getHeaderActions(): array
    {
        $canManage = fn (): bool => AdminAuthorization::can(auth()->user(), 'gateway_routing.manage');

        return [
            Action::make('toggleReorder')
                ->label(fn (): string => $this->isTableReordering ? 'Done reordering' : 'Reorder priorities')
                ->icon('heroicon-o-arrows-up-down')
                ->color('primary')
                ->visible($canManage)
                ->action(fn (): mixed => $this->toggleTableReordering()),
            Action::make('manageGateways')
                ->label('Payment gateways')
                ->url(PaymentGatewayResource::getUrl('index'))
                ->icon('heroicon-o-credit-card')
                ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'payment_gateways.view')),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                SchemaView::make('filament.admin.pages.gateway-routing-overview')
                    ->viewData(fn (): array => [
                        'plan' => app(GatewayRoutingAdminService::class)->routingPlan(),
                    ]),
                EmbeddedTable::make(),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        return PaymentGatewayConfig::query()
            ->orderBy('sort_order')
            ->orderByDesc('weight');
    }

    public function table(Table $table): Table
    {
        $routing = app(GatewayRoutingAdminService::class);

        return $table
            ->heading('Priority queue')
            ->description('Click Reorder priorities (top right), then drag rows by the handle. Or use Move up / Move down on each row. Lower # is tried first at checkout.')
            ->query(fn (): Builder => $this->getTableQuery())
            ->paginated(false)
            ->defaultSort('sort_order')
            ->reorderable(
                'sort_order',
                fn (): bool => AdminAuthorization::can(auth()->user(), 'gateway_routing.manage'),
            )
            ->reorderRecordsTriggerAction(function (Action $action, bool $isReordering): Action {
                return $action
                    ->label($isReordering ? 'Done reordering' : 'Reorder priorities')
                    ->icon('heroicon-o-arrows-up-down')
                    ->button()
                    ->color('primary');
            })
            ->authorizeReorder(fn (): bool => AdminAuthorization::can(auth()->user(), 'gateway_routing.manage'))
            ->afterReordering(function (array $order): void {
                AdminActivityLogger::log(
                    module: 'gateway_routing',
                    event: 'reordered',
                    description: 'Gateway routing priority updated',
                    properties: ['order' => array_values($order)],
                );

                Notification::make()
                    ->title('Gateway priority saved')
                    ->success()
                    ->send();
            })
            ->recordActions([
                Action::make('moveUp')
                    ->label('Move up')
                    ->icon('heroicon-o-arrow-up')
                    ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'gateway_routing.manage'))
                    ->action(function (PaymentGatewayConfig $record) use ($routing): void {
                        if (! $routing->moveGateway($record, -1)) {
                            Notification::make()
                                ->title('Already at top of queue')
                                ->warning()
                                ->send();

                            return;
                        }

                        AdminActivityLogger::log(
                            module: 'gateway_routing',
                            event: 'reordered',
                            description: "Moved gateway {$record->name} up in routing queue",
                            subject: $record,
                        );

                        Notification::make()
                            ->title('Priority updated')
                            ->success()
                            ->send();
                    }),
                Action::make('moveDown')
                    ->label('Move down')
                    ->icon('heroicon-o-arrow-down')
                    ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'gateway_routing.manage'))
                    ->action(function (PaymentGatewayConfig $record) use ($routing): void {
                        if (! $routing->moveGateway($record, 1)) {
                            Notification::make()
                                ->title('Already at bottom of queue')
                                ->warning()
                                ->send();

                            return;
                        }

                        AdminActivityLogger::log(
                            module: 'gateway_routing',
                            event: 'reordered',
                            description: "Moved gateway {$record->name} down in routing queue",
                            subject: $record,
                        );

                        Notification::make()
                            ->title('Priority updated')
                            ->success()
                            ->send();
                    }),
            ])
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (PaymentGatewayConfig $record): string => $record->slug),
                TextColumn::make('provider')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title()),
                TextColumn::make('weight')
                    ->sortable(),
                TextColumn::make('routing_role')
                    ->label('Routing')
                    ->badge()
                    ->state(function (PaymentGatewayConfig $record) use ($routing): string {
                        return $routing->roleForGateway($record)['label'];
                    })
                    ->color(function (PaymentGatewayConfig $record) use ($routing): string {
                        return $routing->roleForGateway($record)['color'];
                    }),
                TextColumn::make('daily_load')
                    ->label('Daily load')
                    ->state(function (PaymentGatewayConfig $record): string {
                        $stats = app(PaymentGatewayLoadService::class)->stats($record);
                        $limit = $stats['daily_limit'] !== null ? number_format($stats['daily_limit'], 0) : '∞';

                        return number_format($stats['daily_volume'], 0).' / '.$limit;
                    }),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                IconColumn::make('failover_enabled')
                    ->label('Failover')
                    ->boolean()
                    ->toggleable(),
            ])
            ->recordUrl(fn (PaymentGatewayConfig $record): string => PaymentGatewayResource::getUrl('view', ['record' => $record]));
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->query(fn (): Builder => $this->getTableQuery());
    }

    public function getPageClasses(): array
    {
        return [
            'fi-gateway-routing-page',
        ];
    }
}
