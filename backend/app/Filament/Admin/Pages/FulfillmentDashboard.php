<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Widgets\Fulfillment\FulfillmentStatsWidget;
use App\Filament\Admin\Widgets\Fulfillment\FulfillmentStatusChartWidget;
use App\Filament\Admin\Widgets\Fulfillment\FulfillmentVolumeChartWidget;
use App\Support\Admin\AdminActivityLogger;
use App\Support\Admin\AdminAuthorization;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class FulfillmentDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::FULFILLMENT;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Fulfillment Dashboard';

    protected static ?string $slug = 'fulfillment';

    public static function canAccess(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'fulfillment.view')
            || AdminAuthorization::can(auth()->user(), 'delay_fulfillment.view');
    }

    public function getTitle(): string|Htmlable
    {
        return 'Fulfillment Dashboard';
    }

    public function mount(): void
    {
        AdminActivityLogger::log(
            module: 'fulfillment',
            event: 'viewed',
            description: 'Fulfillment dashboard viewed',
        );
    }

    /**
     * @return array<class-string>
     */
    public function getFooterWidgets(): array
    {
        return [
            FulfillmentStatsWidget::class,
            FulfillmentStatusChartWidget::class,
            FulfillmentVolumeChartWidget::class,
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)
                ->schema(fn (): array => $this->getWidgetsSchemaComponents($this->getFooterWidgets())),
        ]);
    }
}
