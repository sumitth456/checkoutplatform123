<?php

namespace App\Filament\Admin\Resources\Orders\Pages;

use App\Filament\Admin\Resources\Orders\Concerns\LogsOrderAdminActivity;
use App\Filament\Admin\Resources\Orders\OrderResource;
use App\Filament\Admin\Resources\Orders\RelationManagers\OrderItemsRelationManager;
use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use App\Models\Order;
use App\Modules\Admin\Services\AdminOrderRefundService;
use App\Modules\Order\Enums\OrderPaymentStatus;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Schema;
use RuntimeException;

class ViewOrder extends ViewRecord
{
    use LogsOrderAdminActivity;

    protected static string $resource = OrderResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->load([
            'customer',
            'items.product',
            'items.variant',
            'subscriptions.product',
            'subscriptions.variant',
            'subscriptions.plan',
            'subscriptions.paymentGateway',
            'transactions.paymentGateway',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ...$this->refundActions(),
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected function refundActions(): array
    {
        return [
            Action::make('refund')
                ->label('Issue refund')
                ->icon('heroicon-o-receipt-refund')
                ->color('warning')
                ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'orders.refund'))
                ->visible(fn (Order $record): bool => $this->refundableAmount($record) > 0
                    && in_array($record->payment_status, [OrderPaymentStatus::Paid, OrderPaymentStatus::PartiallyRefunded], true))
                ->form([
                    TextInput::make('amount')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->default(fn (Order $record): float => $this->refundableAmount($record)),
                    TextInput::make('reason')
                        ->maxLength(255),
                ])
                ->action(function (array $data, AdminOrderRefundService $refunds): void {
                    try {
                        $this->record = $refunds->refund(
                            $this->record,
                            (string) $data['amount'],
                            $data['reason'] ?? null,
                        );

                        $this->logOrderActivity(
                            'refunded',
                            "Refunded {$data['amount']} {$this->record->currency}",
                            ['amount' => $data['amount'], 'reason' => $data['reason'] ?? null],
                        );

                        Notification::make()->title('Refund submitted')->success()->send();
                    } catch (RuntimeException $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    protected function refundableAmount(Order $order): float
    {
        return max(0, round((float) $order->grand_total - (float) $order->refunded_total, 2));
    }

    /**
     * @return array<class-string|\Filament\Resources\RelationManagers\RelationGroup|\Filament\Resources\RelationManagers\RelationManagerConfiguration>
     */
    public function getRelationManagers(): array
    {
        return array_values(array_filter(
            parent::getRelationManagers(),
            fn (string|\Filament\Resources\RelationManagers\RelationGroup|\Filament\Resources\RelationManagers\RelationManagerConfiguration $manager): bool => $this->normalizeRelationManagerClass($manager) !== OrderItemsRelationManager::class,
        ));
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Livewire::make(OrderItemsRelationManager::class, fn (): array => [
                'ownerRecord' => $this->getRecord(),
                'pageClass' => static::class,
            ])->key('order-line-items-primary'),
            AdminInfolistComponents::summaryHeader($this->getRecord()),
            $this->getInfolistContentComponent(),
            $this->getRelationManagersContentComponent(),
        ]);
    }
}
